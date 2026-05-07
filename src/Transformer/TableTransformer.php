<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use DOMElement;
use DOMNode;
use Nowo\HtmlToWordBundle\Builder\StyleMapper;
use Nowo\HtmlToWordBundle\Config\ResolvedConfig;
use PhpOffice\PhpWord\Element\AbstractContainer;

use function in_array;

/**
 * HTML tables → PHPWord tables ({@code thead}/{@code tbody}/{@code tfoot}, {@code colspan}, basic styles).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final readonly class TableTransformer implements TransformerInterface
{
    public function __construct(
        private StyleMapper $styleMapper,
    ) {
    }

    public function supports(string $element): bool
    {
        return $element === 'table';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function transform(
        DOMNode $node,
        AbstractContainer $container,
        ResolvedConfig $config,
        DocumentWalkerInterface $walker,
    ): void {
        if (!$node instanceof DOMElement || strtolower($node->nodeName) !== 'table') {
            return;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'caption') {
                $captionRun = $container->addTextRun(['alignment' => 'center', 'spaceAfter' => 120]);
                $walker->appendRichText($child, $captionRun, $config);
            }
        }

        $rows    = $this->collectRows($node);
        $maxCols = $this->maxColumns($rows);
        if ($maxCols === 0) {
            return;
        }

        $cellWidth = (int) (9000 / $maxCols);

        $table = $container->addTable([
            'borderSize'  => 6,
            'borderColor' => '999999',
            'cellMargin'  => 80,
        ]);

        $repeatHeader = (bool) $config->get('tables.repeat_header_row', true);

        foreach ($rows as $rowMeta) {
            /** @var DOMElement $tr */
            $tr       = $rowMeta['tr'];
            $isHeader = (bool) $rowMeta['header'];
            $rowStyle = ($isHeader && $repeatHeader) ? ['tblHeader' => true] : null;
            $table->addRow(null, $rowStyle);

            foreach ($this->collectCells($tr) as $cell) {
                $colspan   = max(1, (int) ($cell->getAttribute('colspan') ?: 1));
                $cellStyle = ['gridSpan' => $colspan];

                $bg = $this->cellBackground($cell, $isHeader);
                if ($bg !== null) {
                    $cellStyle['bgColor'] = $bg;
                }

                $va = $this->verticalAlign($cell);
                if ($va !== null) {
                    $cellStyle['valign'] = $va;
                }

                $width = $cellWidth * $colspan;
                $c     = $table->addCell($width, $cellStyle);

                $pStyle = [];
                if ($isHeader) {
                    $pStyle = array_merge($this->styleMapper->paragraphSpacing($config), ['alignment' => 'center']);
                }

                $run = $c->addTextRun($pStyle);
                $walker->appendRichText($cell, $run, $config);
            }
        }
    }

    /**
     * @return list<array{tr: DOMElement, header: bool}>
     */
    private function collectRows(DOMElement $table): array
    {
        $out = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);
            if ($name === 'caption') {
                continue;
            }

            if ($name === 'tr') {
                $out[] = ['tr' => $child, 'header' => false];

                continue;
            }

            if (!in_array($name, ['thead', 'tbody', 'tfoot'], true)) {
                continue;
            }

            $isHeader = $name === 'thead';
            foreach ($child->childNodes as $tr) {
                if ($tr instanceof DOMElement && strtolower($tr->nodeName) === 'tr') {
                    $out[] = ['tr' => $tr, 'header' => $isHeader];
                }
            }
        }

        return $out;
    }

    /**
     * @param list<array{tr: DOMElement, header: bool}> $rows
     */
    private function maxColumns(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $sum = 0;
            foreach ($this->collectCells($row['tr']) as $cell) {
                $sum += max(1, (int) ($cell->getAttribute('colspan') ?: 1));
            }
            $max = max($max, $sum);
        }

        return $max;
    }

    /**
     * @return list<DOMElement>
     */
    private function collectCells(DOMElement $tr): array
    {
        $cells = [];
        foreach ($tr->childNodes as $c) {
            if ($c instanceof DOMElement) {
                $n = strtolower($c->nodeName);
                if ($n === 'td' || $n === 'th') {
                    $cells[] = $c;
                }
            }
        }

        return $cells;
    }

    private function cellBackground(DOMElement $cell, bool $isHeaderCell): ?string
    {
        $hex = $this->styleMapper->tableCellBackgroundHex($cell->getAttribute('style'));
        if ($hex !== null) {
            return $hex;
        }

        if ($isHeaderCell) {
            return 'EFEFEF';
        }

        return null;
    }

    private function verticalAlign(DOMElement $cell): ?string
    {
        $style = $cell->getAttribute('style');
        if ($style === '' || !preg_match('/vertical-align\s*:\s*([^;]+)/i', $style, $m)) {
            return null;
        }

        return match (strtolower(trim($m[1]))) {
            'top'    => 'top',
            'middle' => 'center',
            'bottom' => 'bottom',
            default  => null,
        };
    }
}
