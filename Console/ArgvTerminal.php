<?php

declare(strict_types=1);

namespace Lumynus\Console;

use Lumynus\Console\Contracts\Terminal;

final class ArgvTerminal implements Terminal
{
    public function __construct(private array $argv)
    {
        $this->argv = array_values($argv);
    }

    /**
     * Returns all raw terminal arguments.
     */
    public function getAll(): array
    {
        return $this->argv;
    }

    /**
     * Returns the name of the invoked command.
     */
    public function command(): string
    {
        return $this->argv[0] ?? '';
    }

    /**
     * Returns the command method, if provided.
     * Strips the "--" prefix when present.
     */
    public function method(): ?string
    {
        $method = $this->argv[1] ?? null;

        if ($method && str_starts_with($method, '--')) {
            return substr($method, 2);
        }

        return $method;
    }

    /**
     * Returns only the parameters passed to the command.
     */
    public function params(): array
    {
        return array_slice($this->argv, 2);
    }
}
