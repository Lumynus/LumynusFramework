<?php

declare(strict_types=1);

namespace Lumynus\Bundle\Framework;

use Lumynus\Bundle\Framework\Luma;
use Lumynus\Bundle\Framework\Sessions;
use Lumynus\Http\HttpResponse;
use Lumynus\Bundle\Framework\Sanitizer;
use Lumynus\Bundle\Framework\Converts;
use Lumynus\Bundle\Framework\LumaClasses;
use Lumynus\Bundle\Framework\LumaHTTP;
use Lumynus\Bundle\Framework\HttpClient;
use Lumynus\Bundle\Framework\Brasil;
use Lumynus\Bundle\Framework\Requirements;
use Lumynus\Bundle\Framework\Regex;
use Lumynus\Bundle\Framework\Encryption;
use Lumynus\Bundle\Framework\Validate;
use Lumynus\Bundle\Framework\Logs;
use Lumynus\Bundle\Framework\Cookies;
use Lumynus\Bundle\Framework\QueueManager;
use Lumynus\Bundle\Framework\CSRF;
use Lumynus\Bundle\Framework\Memory;
use Lumynus\Bundle\Framework\CORS;
use Lumynus\Bundle\Framework\Resolver;

abstract class LumynusController extends LumaClasses
{

    use Requirements;
    use ControllerPipeline;

    /**
     * Método para renderizar uma view com dados.
     *
     * @param string $view Nome da view a ser renderizada.
     * @param array $data Dados a serem passados para a view.
     * @param bool $regenerateCSRF Informa se deseja regernar o CSRF na view
     * @return string Retorna o conteúdo renderizado da view.
     */
    public function renderView(string $view, array $data = [], bool $regenerateCSRF = true): string
    {
        return Luma::render($view, $data, $regenerateCSRF);
    }

    /**
     * Método para obter a instância da classe Sessions.
     * @param array $userOptions Opções personalizadas para a sessão.
     * @return Sessions Retorna uma nova instância da classe Sessions.
     * @throws \Exception Se a sessão não puder ser iniciada.
     */
    public function sessions(array $userOptions = []): Sessions
    {
        return new Sessions($userOptions);
    }

    /**
     * Método para obter a instância da classe Cookie.
     * @return Cookies Retorna uma nova instância da classe Cookie.
     */
    public function cookies(): Cookies
    {
        return new Cookies();
    }

    /**
     * Método para obter a instância da classe Validate.
     * @return Validate Retorna uma nova instância da classe Validate.
     */
    public function validate(): Validate
    {
        return new Validate();
    }

    /**
     * Método para obter a instância da classe Logs.
     * @return Logs Retorna uma nova instância da classe Logs.
     */
    public function logs(): Logs
    {
        return new Logs;
    }

    /**
     * Método para obter a instância da classe Response.
     * @return HttpResponse Retorna uma nova instância da classe Response.
     */
    public function response(): HttpResponse
    {
        return new HttpResponse();
    }

    /**
     * Método para obter a instância da classe Sanitizer.
     * @return Sanitizer Retorna uma nova instância da classe Sanitizer.
     */
    public function sanitizer(): Sanitizer
    {
        return new Sanitizer();
    }

    /**
     * Método para obter a instância da classe Converts.
     * @return Converts Retorna uma nova instância da classe Converts.
     */
    public function converter(): Converts
    {
        return new Converts();
    }

    /**
     * Método para obter a instância da classe Brasil.
     * @return Brasil Retorna uma nova instância da classe Brasil.
     */
    public function brasil(): Brasil
    {
        return new Brasil();
    }

    /**
     * Método para obter a instância da classe LumaHTTP.
     * @return LumaHTTP Retorna uma nova instância da classe LumaHTTP.
     */
    public function lumaHTTP(): LumaHTTP
    {
        return new LumaHTTP();
    }

    /**
     * Método para obter a instância da classe HttpClient.
     * @return HttpClient Retorna uma nova instância da classe HttpClient.
     */
    public function httpClient(): HttpClient
    {
        return new HttpClient();
    }

    /**
     * Método para obter a instância da classe Regex.
     * @return Regex Retorna uma nova instância da classe Regex.
     */
    public function regex(): Regex
    {
        return new Regex();
    }

    /**
     * Método para obter a instância da classe Encryption
     * @return Encryption Retorna uma nova instância da classe Encryption
     */
    public function encryption(): Encryption
    {
        return new Encryption();
    }

    /**
     * Método para obter a instância da classe QueueManager
     * @return QueueManager Retorna uma nova instância da classe QueueManager
     */
    public function queue(): QueueManager
    {
        return new QueueManager;
    }

    /**
     * Método para obter a instância da classe CSRF
     * @return CSRF Retorna uma nova instância da classe CSRF
     */
    public function csrf(): CSRF
    {
        return new CSRF;
    }

    /**
     * Método para obter a instância da classe Memory
     * @return Memory Retorna uma nova instância da classe Memory
     */
    public function memory(): Memory
    {
        return new Memory;
    }

    /**
     * Método para obter a instância da classe CORS.
     * @return CORS Retorna uma nova instância da classe CORS.
     */
    public function cors(): CORS
    {
        return new CORS();
    }

    /**
     * Método para obter a instância da classe Resolver
     * @return Resolver Retorna uma nova instância da classe Resolver
     */
    public function resolver(): Resolver
    {
        return new Resolver;
    }

    /**
     * Método para chamar funções em molde estático
     * @return self
     */
    public static function static(): self
    {
        return new static();
    }

    /**
     * Método para obter a instância da classe Luma.
     * @return Luma Retorna uma nova instância da classe Luma.
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
     * Executa dinamicamente um método da instância atual.
     *
     * @template TReturn
     * @param non-empty-string $method Nome do método a ser executado
     * @param mixed ...$args Argumentos do método
     * @return TReturn Retorno do método executado
     *
     * @throws \RuntimeException Se o método não existir
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
     * Executa dinamicamente um método de outro controller.
     *
     * @template TReturn
     * @param class-string $class Classe do controller
     * @param non-empty-string $method Nome do método a ser executado
     * @param mixed ...$args Argumentos do método
     * @return TReturn Retorno do método executado
     *
     * @throws \RuntimeException Se a classe ou método não existir
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
