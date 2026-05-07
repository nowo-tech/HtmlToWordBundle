<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Config;

use Nowo\HtmlToWordBundle\Exception\InvalidProfileException;

use function array_replace_recursive;
use function sprintf;

/**
 * Resolves profiles: default YAML profile + named profile + ad-hoc array (deepest wins).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class ProfileResolver
{
    /**
     * @param array<string, array<string, mixed>> $profiles
     */
    public function __construct(
        private array $profiles,
        private string $defaultProfile,
    ) {
    }

    /**
     * @param array<string, mixed> $adhoc
     */
    public function resolve(string $profile, array $adhoc = []): ResolvedConfig
    {
        if (!isset($this->profiles[$profile])) {
            throw new InvalidProfileException(sprintf('Profile "%s" is not defined in nowo_html_to_word configuration.', $profile));
        }

        $base   = $this->profiles[$this->defaultProfile] ?? [];
        $named  = $this->profiles[$profile];
        $merged = array_replace_recursive($base, $named, $adhoc);

        return ResolvedConfig::fromArray($merged);
    }

    /**
     * @param array<string, mixed> $adhoc
     */
    public function resolveDefault(array $adhoc = []): ResolvedConfig
    {
        return $this->resolve($this->defaultProfile, $adhoc);
    }

    public function getDefaultProfileKey(): string
    {
        return $this->defaultProfile;
    }
}
