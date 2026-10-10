<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\LumynusContainer;

final class ContainerProxy
{
    /**
     * Retrieves an instance of the requested class.
     * 
     * Useful as it allows reusing the same class instance across different parts of the code.
     * 
     * @param string $class Class namespace to instantiate.
     * @param array $options Constructor arguments array.
     * @param string|null $key Unique container instance key.
     * @return object Resolved class instance.
     */
    public function make(string $class, array $options = [], ?string $key = null): object
    {
        return LumynusContainer::resolve($class, $options, $key);
    }

    /**
     * Retrieves execution call trace.
     * @return string Returns execution call trace string.
     */
    public function getTrace(): string
    {
        return LumynusContainer::getTrace();
    }
}
