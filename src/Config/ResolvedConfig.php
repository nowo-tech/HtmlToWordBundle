<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Config;

use function array_key_exists;
use function is_array;

/**
 * Immutable merged configuration (default profile + named profile + ad-hoc options).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class ResolvedConfig
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private array $data)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Dot-path access: page.margins.top.
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $cur   = $this->data;
        foreach ($parts as $p) {
            if (!is_array($cur) || !array_key_exists($p, $cur)) {
                return $default;
            }
            $cur = $cur[$p];
        }

        return $cur;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function strictMode(): bool
    {
        return (bool) $this->get('strict_mode', false);
    }
}
