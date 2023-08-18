<?php

namespace ValeSaude\TelemedicineClient\Builders;

/**
 * @template TResult
 */
abstract class AbstractBuilder
{
    /** @var array <string, mixed> */
    protected array $properties = [];

    private function __construct()
    {
    }

    /**
     * @return TResult
     */
    abstract public function build();

    /**
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    protected function get(string $property, $default = null)
    {
        return $this->properties[$property] ?? $default;
    }

    /**
     * @param mixed $value
     *
     * @return static
     */
    protected function set(string $property, $value): self
    {
        $this->properties[$property] = $value;

        return $this;
    }

    /**
     * @return static
     */
    public static function new(): self
    {
        return new static();
    }
}