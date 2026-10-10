<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\Luma;
use Lumynus\Framework\Sessions;
use Lumynus\Http\HttpResponse;
use Lumynus\Framework\Sanitizer;
use Lumynus\Framework\Converter;
use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\LumaHTTP;
use Lumynus\Framework\HttpClient;
use Lumynus\Framework\Brasil;
use Lumynus\Framework\Requirements;
use Lumynus\Framework\Regex;
use Lumynus\Framework\Encryption;
use Lumynus\Framework\Validate;
use Lumynus\Framework\Logs;
use Lumynus\Framework\Cookies;
use Lumynus\Framework\QueueManager;
use Lumynus\Framework\CSRF;
use Lumynus\Framework\Memory;
use Lumynus\Framework\CORS;
use Lumynus\Framework\Resolver;
use Lumynus\Framework\LumynusContainer;
use Lumynus\Framework\Helpers;
use Lumynus\Framework\Collection;
use Lumynus\Framework\File;

abstract class AbstractController extends LumaClasses
{

    use Requirements;
    use ControllerPipeline;
    use Helpers;

    /**
     * Renders a view with data.
     *
     * @param string $view View name to render.
     * @param array $data Data to pass to the view.
     * @param bool $regenerateCSRF Whether to regenerate CSRF in the view.
     * @return string Rendered view content.
     */
    public function renderView(string $view, array $data = [], bool $regenerateCSRF = true): string
    {
        return Luma::render($view, $data, $regenerateCSRF);
    }

    /**
     * Retrieves an instance of the Sessions class.
     * @param array $userOptions Custom options for the session.
     * @return Sessions Instance of the Sessions class.
     * @throws \Exception If the session cannot be started.
     */
    public function sessions(array $userOptions = []): Sessions
    {
        if (empty($userOptions) && Sessions::getActiveInstance() !== null) {
            return Sessions::getActiveInstance();
        }

        $key = 'sessions_' . md5(json_encode($userOptions));
        return $this->makeInstance(Sessions::class, [$userOptions], $key);
    }

    /**
     * Retrieves an instance of the Cookie class.
     * @param array $userOptions Custom options for the cookie.
     * @return Cookies Instance of the Cookie class.
     */
    public function cookies(array $userOptions = []): Cookies
    {
        if (empty($userOptions) && Cookies::getActiveInstance() !== null) {
            return Cookies::getActiveInstance();
        }

        $key = 'cookies_' . md5(json_encode($userOptions));
        return $this->makeInstance(Cookies::class, [$userOptions], $key);
    }

    /**
     * Retrieves an instance of the Validate class.
     * @return Validate Instance of the Validate class.
     */
    public function validate(): Validate
    {
        return $this->makeInstance(Validate::class);
    }

    /**
     * Retrieves an instance of the Logs class.
     * @return Logs Instance of the Logs class.
     */
    public function logs(): Logs
    {
        return $this->makeInstance(Logs::class);
    }

    /**
     * Retrieves an instance of the Response class.
     * @return HttpResponse Instance of the Response class.
     */
    public function response(): HttpResponse
    {
        return $this->makeInstance(HttpResponse::class);
    }

    /**
     * Retrieves an instance of the Collection class.
     * @param array $items Array of items for the collection.
     * @return Collection Instance of the Collection class.
     */
    public function collection(array $items): Collection
    {
        return $this->makeInstance(Collection::class, [$items]);
    }

    /**
     * Retrieves an instance of the Sanitizer class.
     * @return Sanitizer Instance of the Sanitizer class.
     */
    public function sanitizer(): Sanitizer
    {
        return $this->makeInstance(Sanitizer::class);
    }

    /**
     * Retrieves an instance of the Converts class.
     * @return Converter Instance of the Converts class.
     */
    public function converter(): Converter
    {
        return $this->makeInstance(Converter::class);
    }

    /**
     * Retrieves an instance of the Brasil class.
     * @return Brasil Instance of the Brasil class.
     */
    public function brasil(): Brasil
    {
        return $this->makeInstance(Brasil::class);
    }

    /**
     * Retrieves an instance of the LumaHTTP class.
     * @return LumaHTTP Instance of the LumaHTTP class.
     */
    public function lumaHTTP(): LumaHTTP
    {
        return new LumaHTTP();
    }

    /**
     * Retrieves an instance of the HttpClient class.
     * @return HttpClient Instance of the HttpClient class.
     */
    public function httpClient(): HttpClient
    {
        return new HttpClient();
    }

    /**
     * Retrieves an instance of the Regex class.
     * @return Regex Instance of the Regex class.
     */
    public function regex(): Regex
    {
        return $this->makeInstance(Regex::class);
    }

    /**
     * Retrieves an instance of the Encryption class.
     * @return Encryption Instance of the Encryption class.
     */
    public function encryption(): Encryption
    {
        return $this->makeInstance(Encryption::class);
    }

    /**
     * Retrieves an instance of the QueueManager class.
     * @return QueueManager Instance of the QueueManager class.
     */
    public function queue(): QueueManager
    {
        return $this->makeInstance(QueueManager::class);
    }

    /**
     * Retrieves an instance of the CSRF class.
     * @return CSRF Instance of the CSRF class.
     */
    public function csrf(): CSRF
    {
        return $this->makeInstance(CSRF::class);
    }

    /**
     * Retrieves an instance of the Memory class.
     * @return Memory Instance of the Memory class.
     */
    public function memory(): Memory
    {
        return $this->makeInstance(Memory::class);
    }

    /**
     * Retrieves an instance of the File class.
     * @return File Instance of the File class.
     */
    public function file(): File
    {
        return $this->makeInstance(File::class);
    }

    /**
     * Retrieves an instance of the Resolver class.
     * @return Resolver Instance of the Resolver class.
     */
    public function resolver(): Resolver
    {
        return $this->makeInstance(Resolver::class);
    }

    /**
     * Generic factory method to create utility class instances.
     * @param string $class Class name to instantiate.
     * @param array $options Constructor options for the class.
     * @param string|null $key Optional cache key to store the instance.
     * @return object New instance of the specified class.
     */
    private function makeInstance(string $class, array $options = [], ?string $key = null)
    {
        return LumynusContainer::resolve($class, $options, $key);
    }

    /**
     * Creates a new static instance.
     * @return self
     */
    public static function static(mixed ...$args): static
    {
        return new static(...$args);
    }

    /**
     * Retrieves an instance of the Luma class.
     * @return Luma Instance of the Luma class.
     */
    public function __debugInfo(): array
    {
        return [
            'Lumynus' => "Framework PHP"
        ];
    }
}

trait ControllerPipeline
{
    /**
     * Dynamically executes a method on the current instance.
     *
     * @template TReturn
     * @param non-empty-string $method Method name to execute.
     * @param mixed ...$args Method arguments.
     * @return TReturn Return value of executed method.
     *
     * @throws \RuntimeException If method does not exist.
     */
    public function next(string $method, mixed ...$args): mixed
    {
        if (!method_exists($this, $method)) {
            throw new \RuntimeException(
                "Método {$method} não existe em " . static::class
            );
        }

        return $this->{$method}(...$args);
    }

    /**
     * Dynamically executes a method on another controller.
     *
     * @template TReturn
     * @param class-string $class Controller class.
     * @param non-empty-string $method Method name to execute.
     * @param mixed ...$args Method arguments.
     * @return TReturn Return value of executed method.
     *
     * @throws \RuntimeException If class or method does not exist.
     */
    public function nextTo(string $class, string $method, mixed ...$args): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException(
                "Classe {$class} não existe."
            );
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            throw new \RuntimeException(
                "Método {$method} não existe em {$class}."
            );
        }

        return $instance->{$method}(...$args);
    }
}
