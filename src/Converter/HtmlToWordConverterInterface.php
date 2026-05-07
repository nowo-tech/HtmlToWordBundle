<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Converter;

use Nowo\HtmlToWordBundle\Exception\InvalidProfileException;
use Nowo\HtmlToWordBundle\Model\WordDocument;

/**
 * Converts sanitized HTML (WYSIWYG output) into a {@see WordDocument} using the configured engine (`nowo_html_to_word.engine`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
interface HtmlToWordConverterInterface
{
    /**
     * Converts HTML using the configured default profile.
     */
    public function convert(string $html): WordDocument;

    /**
     * Converts HTML using a named YAML profile.
     *
     * @throws InvalidProfileException if the profile does not exist
     */
    public function convertWithProfile(string $html, string $profile): WordDocument;

    /**
     * Converts HTML merging a base profile with ad-hoc options (deepest wins).
     *
     * When {@see $profile} is null, the configured `default_profile` is used.
     *
     * @param array<string, mixed> $options same shape as a single YAML profile (subset allowed); merged after named profile
     *
     * @throws InvalidProfileException if the profile does not exist
     */
    public function convertWithOptions(string $html, array $options = [], ?string $profile = null): WordDocument;

    /**
     * Converts HTML using a profile-shaped configuration only (no YAML merge).
     *
     * Use this when the full profile is stored elsewhere (e.g. database). Structure matches one entry under
     * `nowo_html_to_word.profiles` in bundle configuration.
     *
     * @param array<string, mixed> $profileConfig
     */
    public function convertWithInlineProfile(string $html, array $profileConfig): WordDocument;
}
