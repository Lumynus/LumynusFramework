<?php

declare(strict_types=1);

namespace Lumynus\Console\Contracts;

interface Terminal
{
    /**
     * Returns all arguments passed to the command.
     *
     * @return array
     */
    public function getAll(): array;

    /**
     * Returns the primary command name.
     *
     * @return string
     */
    public function command(): string;

    /**
     * Returns the invoked method name.
     *
     * @return string|null
     */
    public function method(): ?string;

    /**
     * Returns the command parameters.
     *
     * @return array
     */
    public function params(): array;
}
