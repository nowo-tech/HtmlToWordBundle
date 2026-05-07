<?php

declare(strict_types=1);

namespace Nowo\HtmlToWordBundle\Engine;

use Nowo\HtmlToWordBundle\Exception\UnknownEngineException;

/**
 * Resolves {@see WordEngineInterface} instances by {@see WordEngineInterface::getName()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 */
final class EngineRegistry
{
    /** @var array<string, WordEngineInterface> */
    private array $enginesByName = [];

    /**
     * @param iterable<int, WordEngineInterface> $engines Tagged `html_to_word.engine` implementations.
     */
    public function __construct(iterable $engines)
    {
        foreach ($engines as $engine) {
            $this->enginesByName[$engine->getName()] = $engine;
        }
    }

    public function get(string $name): WordEngineInterface
    {
        if (!isset($this->enginesByName[$name])) {
            throw UnknownEngineException::create($name, array_keys($this->enginesByName));
        }

        return $this->enginesByName[$name];
    }

    /**
     * @return list<string>
     */
    public function registeredNames(): array
    {
        return array_keys($this->enginesByName);
    }
}
