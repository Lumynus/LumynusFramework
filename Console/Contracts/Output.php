<?php

declare(strict_types=1);

namespace Lumynus\Console\Contracts;

interface Output
{
    /**
     * Exits the command with a success message.
     *
     * @param string $message Success message to display.
     * @return self
     */
    public function success(string $message): self;

    /**
     * Outputs an informational message.
     *
     * @param string $message Informational message to display.
     * @param string $colorANSI ANSI color for the message.
     * @return self
     */
    public function info(string $message, string $colorANSI): self;

    /**
     * Outputs an error message.
     *
     * @param string $message Error message to display.
     * @return self
     */
    public function error(string $message): self;
    
    /**
     * Prompts the user with a question.
     *
     * @param string      $message   Message to display to the user.
     * @param string|null $colorANSI Message color (default white). Example: "\033[94m" for blue.
     * @return string
     */
    public function question(string $message, string $colorANSI = "\033[37m"): string;

    /**
     * Responds to the command output.
     *
     * @param string      $message   Message to display to the user.
     * @param string|null $colorANSI Message color (default white). Example: "\033[94m" for blue.
     * @return self
     */
    public function respond(string $message, string $colorANSI = "\033[37m"): self;

    /**
     * Interrupts the running command execution.
     *
     * @return void
     */
    public function interrupt(): void;
}
