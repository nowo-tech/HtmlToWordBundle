<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Converter;

use Nowo\HtmlToWordBundle\Config\ProfileResolver;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\EngineRegistry;
use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use Nowo\HtmlToWordBundle\Exception\EngineNotAvailableException;
use Nowo\HtmlToWordBundle\Exception\HtmlParseException;
use Nowo\HtmlToWordBundle\Model\WordDocument;

use function sprintf;
use function strlen;
use function strtolower;
use function substr_count;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class HtmlToWordConverter implements HtmlToWordConverterInterface
{
    /** Default limits.max_html_bytes (32 MiB, embedded data: images included). */
    public const DEFAULT_MAX_HTML_BYTES = 33554432;

    /** Default images.max_count. */
    public const DEFAULT_MAX_IMAGES = 200;

    public function __construct(
        private ProfileResolver $profiles,
        private EngineRegistry $engines,
        private string $engineName,
    ) {
    }

    public function convert(string $html): WordDocument
    {
        return $this->build($html, $this->profiles->resolveDefault());
    }

    public function convertWithProfile(string $html, string $profile): WordDocument
    {
        return $this->build($html, $this->profiles->resolve($profile));
    }

    public function convertWithOptions(string $html, array $options = [], ?string $profile = null): WordDocument
    {
        $key = $profile ?? $this->profiles->getDefaultProfileKey();

        return $this->build($html, $this->profiles->resolve($key, $options));
    }

    public function convertWithInlineProfile(string $html, array $profileConfig): WordDocument
    {
        return $this->build($html, ResolvedConfig::fromArray($profileConfig));
    }

    /**
     * Rejects oversized input before parsing: the HTML (data: images included) and the number of <img> tags are capped
     * per profile, so one request cannot decode hundreds of embedded images or download hundreds of remote ones.
     */
    private function build(string $html, ResolvedConfig $config): WordDocument
    {
        $maxHtmlBytes = (int) $config->get('limits.max_html_bytes', self::DEFAULT_MAX_HTML_BYTES);
        if (strlen($html) > $maxHtmlBytes) {
            throw new HtmlParseException(sprintf('HTML exceeds limits.max_html_bytes (%d bytes).', $maxHtmlBytes));
        }

        $maxImages = (int) $config->get('images.max_count', self::DEFAULT_MAX_IMAGES);
        if (substr_count(strtolower($html), '<img') > $maxImages) {
            throw new HtmlParseException(sprintf('HTML has more than images.max_count (%d) images.', $maxImages));
        }

        return $this->engine()->build($html, $config);
    }

    private function engine(): WordEngineInterface
    {
        $engine = $this->engines->get($this->engineName);
        if (!$engine->isAvailable()) {
            throw EngineNotAvailableException::forEngine($engine);
        }

        return $engine;
    }
}
