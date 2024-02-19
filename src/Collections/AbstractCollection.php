<?php

namespace ValeSaude\TelemedicineClient\Collections;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;

/**
 * @template            TSubject
 * @template-implements IteratorAggregate<int, TSubject>
 */
abstract class AbstractCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @var array<int, TSubject>
     */
    protected array $items;

    /**
     * @param array<array-key, TSubject> $items
     */
    final public function __construct(array $items = [])
    {
        $expectedClass = $this->getSubjectClass();
        $classBasename = class_basename($expectedClass);

        foreach ($items as $item) {
            if (!is_a($item, $expectedClass)) {
                throw new InvalidArgumentException("Every item must be an instance of {$classBasename}.");
            }
        }

        $this->items = array_values($items);
    }

    /**
     * @return class-string<TSubject>
     */
    abstract public function getSubjectClass(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @return ArrayIterator<int, TSubject>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @param TSubject $item
     *
     * @return static
     */
    public function add($item): self
    {
        $expectedClass = $this->getSubjectClass();

        if (!is_a($item, $expectedClass)) {
            $classBasename = class_basename($expectedClass);

            throw new InvalidArgumentException("The item must be an instance of {$classBasename}.");
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * @param callable(TSubject): mixed $callback
     *
     * @return array<int, mixed>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->getItems());
    }

    /**
     * @param callable(TSubject): mixed $callback
     *
     * @return static
     */
    public function filter(callable $callback): self
    {
        return new static(array_filter($this->getItems(), $callback));
    }

    /**
     * @param callable(TSubject): bool $callback
     */
    public function contains(callable $callback): bool
    {
        foreach ($this->items as $item) {
            if (true === $callback($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return TSubject
     */
    public function at(int $index)
    {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException('The given index does not exist in items array.');
        }

        return $this->items[$index];
    }

    /**
     * @return array<int, TSubject>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * @param array<int, TSubject> $items
     *
     * @return static
     */
    public static function make(array $items = []): self
    {
        return new static($items);
    }
}