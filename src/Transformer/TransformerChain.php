<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Transformer;

use Traversable;

/**
 * Ordered transformers (priority descending).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class TransformerChain
{
    /** @var list<TransformerInterface> */
    private array $sorted;

    /**
     * @param iterable<TransformerInterface> $transformers
     */
    public function __construct(iterable $transformers)
    {
        $items        = $transformers instanceof Traversable ? iterator_to_array($transformers, false) : $transformers;
        $this->sorted = array_values($items);
        usort($this->sorted, static fn (TransformerInterface $a, TransformerInterface $b): int => $b->getPriority() <=> $a->getPriority());
    }

    public function firstSupporting(string $tag): ?TransformerInterface
    {
        foreach ($this->sorted as $t) {
            if ($t->supports($tag)) {
                return $t;
            }
        }

        return null;
    }

    /**
     * @return list<TransformerInterface>
     */
    public function all(): array
    {
        return $this->sorted;
    }
}
