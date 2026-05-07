<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Converter;

use Nowo\HtmlToWordBundle\Config\ProfileResolver;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use Nowo\HtmlToWordBundle\Engine\EngineRegistry;
use Nowo\HtmlToWordBundle\Engine\WordEngineInterface;
use Nowo\HtmlToWordBundle\Exception\EngineNotAvailableException;
use Nowo\HtmlToWordBundle\Model\WordDocument;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class HtmlToWordConverter implements HtmlToWordConverterInterface
{
    public function __construct(
        private ProfileResolver $profiles,
        private EngineRegistry $engines,
        private string $engineName,
    ) {
    }

    public function convert(string $html): WordDocument
    {
        return $this->engine()->build($html, $this->profiles->resolveDefault());
    }

    public function convertWithProfile(string $html, string $profile): WordDocument
    {
        return $this->engine()->build($html, $this->profiles->resolve($profile));
    }

    public function convertWithOptions(string $html, array $options = [], ?string $profile = null): WordDocument
    {
        $key = $profile ?? $this->profiles->getDefaultProfileKey();

        return $this->engine()->build($html, $this->profiles->resolve($key, $options));
    }

    public function convertWithInlineProfile(string $html, array $profileConfig): WordDocument
    {
        return $this->engine()->build($html, ResolvedConfig::fromArray($profileConfig));
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
