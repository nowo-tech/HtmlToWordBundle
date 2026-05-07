<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle;

use Nowo\HtmlToWordBundle\DependencyInjection\HtmlToWordExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * HTML → Word (.docx) conversion with YAML profiles and pluggable engines (default: PHPWord).
 *
 * The bundle name {@code HtmlToWordBundle} maps to the extension alias {@code nowo_html_to_word}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HtmlToWordBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof HtmlToWordExtension) {
            $this->extension = new HtmlToWordExtension();
        }

        return $this->extension;
    }
}
