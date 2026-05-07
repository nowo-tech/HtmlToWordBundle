<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\Section;

use function is_string;

/**
 * Adds optional header/footer sections with logo text and page numbers.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class HeaderFooterBuilder
{
    public function apply(Section $section, ResolvedConfig $config): void
    {
        if ((bool) $config->get('header.enabled', false)) {
            $header = $section->addHeader();
            $logo   = $config->get('header.logo');
            if (is_string($logo) && $logo !== '' && is_readable($logo) && ImageSignatureValidator::isRasterImage($logo)) {
                $targetW = (int) $config->get('header.logo_width', 100);
                $header->addImage($logo, ImageStyleHelper::headerLogoStyle($logo, $targetW > 0 ? $targetW : 100));
            }
            $ht = $config->get('header.text');
            if (is_string($ht) && $ht !== '') {
                $header->addText($ht);
            }
        }

        if ((bool) $config->get('footer.enabled', false)) {
            $footer = $section->addFooter();
            $ft     = $config->get('footer.text');
            if (is_string($ft) && $ft !== '') {
                $footer->addText($ft);
            }
            if ((bool) $config->get('footer.show_page_number', true)) {
                $footer->addPreserveText('{PAGE}', null, ['alignment' => 'center']);
            }
        }
    }
}
