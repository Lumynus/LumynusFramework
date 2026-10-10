<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Console\Contracts\Output;

abstract class AbstractCommand extends LumaClasses implements Output
{
    use \Lumynus\Framework\LumynusUtilities;
    use \Lumynus\Framework\Helpers;

    public function __construct()
    {
        if (PHP_SAPI !== 'cli') {
            throw new \RuntimeException(
                'Commands can only be executed via CLI.'
            );
        }
    }

    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }


    private const GREEN = "\033[32m";
    private const RED   = "\033[31m";
    private const RESET = "\033[0m";

    private bool $responded = false;

    /**
     * Responds to the command.
     *
     * @return self
     */
    protected function output(): self
    {
        return $this;
    }

    /**
     * Prompts the user with a question.
     *
     * @param string      $message   Message to display to the user.
     * @param string|null $colorANSI Message color (default white).
     * @return string
     */
    public function question(string $message, string $colorANSI = "\033[37m"): string
    {
        $this->responded = true;
        echo PHP_EOL . $colorANSI . $message . self::RESET . PHP_EOL;
        return readline();
    }

    /**
     * Outputs a success message.
     *
     * @param string $message
     * @return self
     */
    public function success(string $message): self
    {
        $this->responded = true;
        echo self::GREEN . $message . self::RESET . PHP_EOL;
        return $this;
    }

    /**
     * Outputs an informational message.
     *
     * @param string $message
     * @return self
     */
    public function info(string $message, string $colorANSI = "\033[94m"): self
    {
        $this->responded = true;
        echo $colorANSI . $message . self::RESET . PHP_EOL;
        return $this;
    }

    /**
     * Responds to the command.
     *
     * @param string      $message   Message to display to the user.
     * @param string|null $colorANSI Message color (default white). Example: "\033[94m" for blue.
     * @return self
     */
    public function respond(string $message, string $colorANSI = "\033[37m"): Output
    {
        $this->responded = true;
        echo $colorANSI . $message . self::RESET . PHP_EOL;
        return $this;
    }

    /**
     * Outputs an error message.
     *
     * @param string      $message
     * @param string|null $logMessage
     * @return self
     */
    public function error(string $message, ?string $logMessage = null): self
    {
        $this->responded = true;
        $this->logs()->register('Error in command: ', ($logMessage ?? $message));
        echo self::RED . $message . self::RESET . PHP_EOL;
        return $this;
    }

    /**
     * Interrupts running command execution.
     * @return void
     */
    public function interrupt(): void
    {
        $this->responded = true;
        echo self::RED . "Command interrupted." . self::RESET . PHP_EOL;
        exit(1);
    }


    /**
     * Creates a new static instance.
     * @return self
     */
    protected static function static(): static
    {
        return new static();
    }

    /**
     * Executes an operating system command.
     *
     * @param string $command Command string to execute.
     * @return array Process execution result.
     * @throws \RuntimeException If process cannot be started.
     */
    protected function runProcess(string $command): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Windows adjustment
        if ($this->isWindows()) {
            $command = 'cmd /C ' . $command;
        }

        $process = proc_open($command, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start process.');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout'   => trim($stdout),
            'stderr'   => trim($stderr),
            'exitCode' => $exitCode,
        ];
    }
}
