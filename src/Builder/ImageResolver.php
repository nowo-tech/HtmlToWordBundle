<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Exception\ImageResolveException;
use Nowo\HtmlToWordBundle\Parser\RemoteHttpImageInliner;
use Nowo\HtmlToWordBundle\Security\RemoteAddressGuard;
use Nowo\HtmlToWordBundle\Security\RemoteImageHostPolicy;
use Psr\Log\LoggerInterface;

use function fclose;
use function feof;
use function fopen;
use function fread;
use function in_array;
use function intdiv;
use function is_string;
use function parse_url;
use function preg_match;
use function realpath;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function stream_context_create;
use function stream_get_meta_data;
use function strlen;
use function strstr;
use function strtolower;
use function trim;

use const DIRECTORY_SEPARATOR;
use const PHP_URL_HOST;

/**
 * Resolves <img src> to a temporary local path for PHPWord.
 *
 * Supports data URIs, http(s) URLs (allowlisted public hosts, no redirects, size-capped) and local paths only when they
 * are temp files created by this bundle for the current conversion or live inside {@code images.local_base_dir}.
 * Any other scheme or stream wrapper ({@code file://}, {@code ftp://}, {@code php://}, {@code phar://}…) is rejected.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class ImageResolver implements ImageResolverInterface
{
    /** Default cap for remote image downloads (bytes). */
    public const DEFAULT_REMOTE_MAX_BYTES = 10485760;

    /** Default cap for decoded data: URI images (bytes). */
    public const DEFAULT_DATA_URI_MAX_BYTES = 10485760;

    private RemoteAddressGuard $addressGuard;

    /**
     * @param TemporaryImageFiles|null $temporaryFiles Registry that deletes downloaded images after the conversion
     * @param RemoteAddressGuard|null $addressGuard SSRF guard resolving hosts to validated public IPs
     * @param LoggerInterface|null $logger Logs remote downloads (host and size only, never the URL query) (REQ-OBS-001)
     */
    public function __construct(
        private ?TemporaryImageFiles $temporaryFiles = null,
        ?RemoteAddressGuard $addressGuard = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->addressGuard = $addressGuard ?? new RemoteAddressGuard();
    }

    /**
     * @throws ImageResolveException
     */
    public function resolveToTempPath(string $src, ResolvedConfig $config): string
    {
        $src = trim($src);
        if ($src === '') {
            throw new ImageResolveException('Empty image src.');
        }

        if (str_starts_with($src, 'data:')) {
            $path = $this->fromDataUri($src, $config);
            $this->assertRasterImage($path, true);
            $this->temporaryFiles?->register($path);

            return $path;
        }

        if ($this->isHttpUrl($src)) {
            return $this->fromHttpUrl($src, $config);
        }

        // Any other scheme / stream wrapper (file://, ftp://, php://, phar://, glob://…) is never read from user HTML.
        // Windows drive letters (C:\\, C:/) are paths, not schemes.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $src) === 1 && preg_match('#^[a-z]:[\\\\/]#i', $src) !== 1) {
            throw new ImageResolveException(sprintf('Unsupported image src scheme: %s', $src));
        }

        $local = $this->resolveLocalPath($src, $config);
        if ($local !== null) {
            $this->assertRasterImage($local, false);

            return $local;
        }

        throw new ImageResolveException(sprintf('Unsupported or unreadable image src: %s', $src));
    }

    /**
     * Local paths are accepted only when they are temp files tracked for the current conversion (remote images
     * inlined by {@see RemoteHttpImageInliner}) or real files inside images.local_base_dir.
     */
    private function resolveLocalPath(string $src, ResolvedConfig $config): ?string
    {
        if ($this->temporaryFiles instanceof TemporaryImageFiles && @is_readable($src)) {
            $real    = realpath($src);
            $pending = $this->temporaryFiles->pending();
            if (in_array($src, $pending, true) || ($real !== false && in_array($real, $pending, true))) {
                return $src;
            }
        }

        $baseDir = $config->get('images.local_base_dir');
        if (!is_string($baseDir) || $baseDir === '') {
            return null;
        }
        $baseReal = realpath($baseDir);
        if ($baseReal === false) {
            return null;
        }
        $isAbsolute = str_starts_with($src, '/') || str_starts_with($src, '\\') || preg_match('#^[a-z]:[\\\\/]#i', $src) === 1;
        $real       = realpath($isAbsolute ? $src : $baseReal . DIRECTORY_SEPARATOR . $src);
        $prefix     = rtrim($baseReal, '/\\') . DIRECTORY_SEPARATOR;
        if ($real === false || !str_starts_with($real, $prefix) || !@is_readable($real)) {
            return null;
        }

        return $real;
    }

    private function isHttpUrl(string $src): bool
    {
        $lower = strtolower($src);

        return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
    }

    /**
     * @throws ImageResolveException
     */
    private function fromHttpUrl(string $src, ResolvedConfig $config): string
    {
        if (!(bool) $config->get('images.resolve_remote', false)) {
            throw new ImageResolveException('Remote image resolution disabled by profile.');
        }

        /** @var list<string> $allowlist */
        $allowlist = $config->get('images.remote_host_allowlist', []);
        if (!RemoteImageHostPolicy::isAllowed($src, $allowlist)) {
            throw new ImageResolveException('Remote image host is not in the configured allowlist.');
        }

        $timeout = (float) $config->get('images.remote_timeout', 10.0);
        if ($timeout < 0.1) {
            $timeout = 0.1;
        }
        $maxBytes = (int) $config->get('images.remote_max_bytes', self::DEFAULT_REMOTE_MAX_BYTES);
        if ($maxBytes < 1) {
            $maxBytes = self::DEFAULT_REMOTE_MAX_BYTES;
        }
        $context = ['bundle' => 'html_to_word', 'action' => 'remote_image', 'host' => (string) parse_url($src, PHP_URL_HOST)];
        try {
            $raw = $this->download($src, $timeout, $maxBytes, (bool) $config->get('images.remote_allow_private_network', false));
        } catch (ImageResolveException $e) {
            // The exception message ends with the full URL (it may carry tokens in its query): log only the reason.
            $reason = strstr($e->getMessage(), ': ', true);
            $this->logger?->warning('Remote image download failed.', $context + ['reason' => $reason === false ? $e->getMessage() : $reason]);

            throw $e;
        }
        $this->logger?->info('Remote image downloaded.', $context + ['bytes' => strlen($raw)]);

        $tmp = tempnam(sys_get_temp_dir(), 'htw_img_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ImageResolveException('Could not create temp file.');
        }
        // @codeCoverageIgnoreEnd

        file_put_contents($tmp, $raw);
        $this->assertRasterImage($tmp, true);
        $this->temporaryFiles?->register($tmp);

        return $tmp;
    }

    /**
     * Downloads a remote image without following redirects, pinned to a validated public IP, aborting above $maxBytes.
     *
     * @throws ImageResolveException
     */
    private function download(string $src, float $timeout, int $maxBytes, bool $allowPrivateNetwork): string
    {
        // The host allowlist already rejected URLs without a host; an empty host fails the address check below.
        $parts  = (array) parse_url($src);
        $host   = (string) ($parts['host'] ?? '');
        $scheme = strtolower((string) ($parts['scheme'] ?? 'http'));

        $connectUrl = $src;
        $http       = ['timeout' => $timeout, 'follow_location' => 0, 'max_redirects' => 1, 'ignore_errors' => true];
        $ssl        = [];
        if (!$allowPrivateNetwork) {
            $ip = $this->addressGuard->resolvePublicIp($host);
            if ($ip === null) {
                throw new ImageResolveException(sprintf('Remote image host resolves to a non-public or unknown address: %s', $host));
            }
            // Pin the connection to the validated IP (no second DNS lookup → no rebinding); keep Host / TLS name.
            $ipHost         = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $port           = isset($parts['port']) ? ':' . $parts['port'] : '';
            $connectUrl     = $scheme . '://' . $ipHost . $port . ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            $http['header'] = 'Host: ' . $host . $port;
            $ssl            = ['peer_name' => trim($host, '[]'), 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true];
        }

        $ctx = stream_context_create(['http' => $http, 'ssl' => $ssl]);
        $fh  = @fopen($connectUrl, 'r', false, $ctx);
        if ($fh === false) {
            throw new ImageResolveException(sprintf('Could not download image: %s', $src));
        }

        try {
            $meta   = stream_get_meta_data($fh);
            $status = 0;
            foreach ((array) ($meta['wrapper_data'] ?? []) as $line) {
                if (is_string($line) && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                    $status = (int) $m[1];
                }
            }
            if ($status < 200 || $status >= 300) {
                throw new ImageResolveException(sprintf('Could not download image (HTTP %d, redirects are not followed): %s', $status, $src));
            }

            $raw = '';
            while (!feof($fh)) {
                $chunk = fread($fh, 8192);
                if ($chunk === false) {
                    // @codeCoverageIgnoreStart
                    throw new ImageResolveException(sprintf('Could not download image: %s', $src));
                    // @codeCoverageIgnoreEnd
                }
                $raw .= $chunk;
                if (strlen($raw) > $maxBytes) {
                    throw new ImageResolveException(sprintf('Remote image exceeds images.remote_max_bytes (%d bytes): %s', $maxBytes, $src));
                }
            }
        } finally {
            fclose($fh);
        }

        return $raw;
    }

    /**
     * @throws ImageResolveException
     */
    private function fromDataUri(string $src, ResolvedConfig $config): string
    {
        if (!preg_match('#^data:image/[^;]+;base64,(.+)$#', $src, $m)) {
            throw new ImageResolveException('Invalid data URI image.');
        }
        // Checked on the encoded length (decoded size is 3/4 of it), before allocating the decoded copy.
        $maxBytes = (int) $config->get('images.data_uri_max_bytes', self::DEFAULT_DATA_URI_MAX_BYTES);
        if (intdiv(strlen($m[1]) * 3, 4) > $maxBytes) {
            throw new ImageResolveException(sprintf('Data URI image exceeds images.data_uri_max_bytes (%d bytes).', $maxBytes));
        }
        $bin = base64_decode($m[1], true);
        if ($bin === false) {
            throw new ImageResolveException('Invalid base64 image data.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'htw_b64_');
        // @codeCoverageIgnoreStart
        if ($tmp === false) {
            throw new ImageResolveException('Could not create temp file.');
        }
        // @codeCoverageIgnoreEnd
        file_put_contents($tmp, $bin);

        return $tmp;
    }

    /**
     * @throws ImageResolveException
     */
    private function assertRasterImage(string $path, bool $unlinkTempOnFailure): void
    {
        if (ImageSignatureValidator::isRasterImage($path)) {
            return;
        }

        if ($unlinkTempOnFailure && str_starts_with($path, sys_get_temp_dir())) {
            @unlink($path);
        }

        throw new ImageResolveException('Resolved file is not a supported raster image (PNG, JPEG, GIF, WebP). Remote URLs that return HTML or JSON are rejected.');
    }
}
