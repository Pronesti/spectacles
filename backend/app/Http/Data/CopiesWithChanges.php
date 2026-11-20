<?php

namespace App\Http\Data;

/**
 * Lets an immutable Data object produce a modified copy of itself.
 */
trait CopiesWithChanges
{
    /**
     * Returns a copy whose named constructor arguments are replaced by the given ones.
     */
    public function copyWith(mixed ...$changes): static
    {
        $arguments = [];
        foreach ((new \ReflectionMethod(static::class, '__construct'))->getParameters() as $parameter) {
            $arguments[$parameter->getName()] = $this->{$parameter->getName()};
        }

        return new static(...[...$arguments, ...$changes]);
    }
}
