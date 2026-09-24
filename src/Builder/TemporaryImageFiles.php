<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Builder;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_last;
use function array_keys;
use function array_pop;
use function is_file;
use function unlink;

/**
 * Tracks temp image files created while building a document (data URIs, remote downloads).
 *
 * Each {@see beginCollection()} / {@see endCollection()} pair returns the files of one conversion, so the
 * exporter can delete exactly those files after PHPWord copied them into the DOCX. Files that were never
 * released (document built but not exported, exception) are deleted on {@code kernel.terminate} and on
 * {@see reset()}, so a long-lived worker never keeps them past the request.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class TemporaryImageFiles implements ResetInterface, EventSubscriberInterface
{
    /** @var array<string, true> path => true, every file not yet released */
    private array $pending = [];

    /** @var list<array<string, true>> */
    private array $collections = [];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => ['releaseAll', -1024]];
    }

    public function register(string $path): void
    {
        if ($path === '') {
            return;
        }

        $this->pending[$path] = true;
        $last                 = array_key_last($this->collections);
        if ($last !== null) {
            $this->collections[$last][$path] = true;
        }
    }

    public function beginCollection(): void
    {
        $this->collections[] = [];
    }

    /**
     * @return list<string> files registered since the matching {@see beginCollection()}
     */
    public function endCollection(): array
    {
        $collection = array_pop($this->collections);

        return $collection === null ? [] : array_keys($collection);
    }

    /**
     * @param iterable<string> $paths
     */
    public function release(iterable $paths): void
    {
        foreach ($paths as $path) {
            unset($this->pending[$path]);
            foreach (array_keys($this->collections) as $i) {
                unset($this->collections[$i][$path]);
            }
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function releaseAll(): void
    {
        $paths             = array_keys($this->pending);
        $this->pending     = [];
        $this->collections = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        return array_keys($this->pending);
    }

    public function reset(): void
    {
        $this->releaseAll();
    }
}
