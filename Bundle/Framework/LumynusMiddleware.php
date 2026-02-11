<?php

declare(strict_types=1);

namespace Lumynus\Bundle\Framework;

use Throwable;
use Lumynus\Bundle\Framework\Luma;
use Lumynus\Bundle\Framework\Sessions;
use Lumynus\Http\HttpResponse;
use Lumynus\Bundle\Framework\Sanitizer;
use Lumynus\Bundle\Framework\Converts;
use Lumynus\Bundle\Framework\LumaClasses;
use Lumynus\Bundle\Framework\LumaHTTP;
use Lumynus\Bundle\Framework\HttpClient;
use Lumynus\Bundle\Framework\CORS;
use Lumynus\Bundle\Framework\Requirements;
use Lumynus\Bundle\Framework\Regex;
use Lumynus\Bundle\Framework\Encryption;
use Lumynus\Bundle\Framework\Validate;
use Lumynus\Bundle\Framework\Logs;
use Lumynus\Bundle\Framework\Cookies;
use Lumynus\Bundle\Framework\QueueManager;
use Lumynus\Bundle\Framework\CSRF;
use Lumynus\Bundle\Framework\Memory;
use Lumynus\Bundle\Framework\Resolver;
use Lumynus\Http\Contracts\Request;
use Lumynus\Http\Contracts\Response;

abstract class LumynusMiddleware extends LumaClasses
{

    use Requirements;


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
     * Método para obter a instância da classe CORS.
     * @return CORS Retorna uma nova instância da classe CORS.
     */
    public function cors(): CORS
    {
        return new CORS();
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
     * Analisa a execução do middleware, coletando dados contextuais
     * da requisição e métricas de tempo para fins de observabilidade
     * e auditoria.
     *
     * @param Request $req Instância da requisição HTTP analisada.
     *
     * @return void
     */
    public function analyze(Request $req): void
    {
        $now = microtime(true);
        $durationSeconds = $now - $this->LUMA_START;

        $this->logs()->register('Middleware analyze', [
            'middleware'        => static::class,
            'method'            => $req->getMethod(),
            'uri'               => (string) $req->getUri(),
            'query'             => $req->getQueryParams(),
            'headers'           => $req->getHeaders(),
            'body'              => $req->getParsedBody(),
            'attributes'        => $req->getAttributes(),
            'start_time'        => $this->LUMA_START,
            'end_time'          => $now,
            'duration_seconds' => round($durationSeconds, 6),
            'duration_ms'       => round($durationSeconds * 1000, 2),
        ]);
    }

    /**
     * Registra métricas básicas de execução do middleware.
     *
     * Coleta informações essenciais da requisição e o tempo total de
     * processamento desde o início do ciclo do middleware, registrando
     * a duração em milissegundos.
     *
     * Este método é destinado exclusivamente ao uso interno de middlewares,
     * não devendo ser utilizado em controllers ou regras de negócio.
     *
     * @param Request $req
     *        Instância da requisição HTTP utilizada para coleta das métricas.
     *
     * @return void
     */
    public function metrics(Request $req): void
    {
        $seconds = microtime(true) - $this->LUMA_START;

        $this->logs()->register('Middleware metrics', [
            'middleware'        => static::class,
            'method'            => $req->getMethod(),
            'uri'               => (string) $req->getUri(),
            'duration_seconds'  => round($seconds, 6),
            'duration_ms'       => round($seconds * 1000, 2)
        ]);
    }

    /**
     * Interrompe a execução do middleware caso a resposta contenha o header
     * "Content-Type", permitindo que a resposta seja retornada imediatamente.
     *
     * Caso a resposta seja nula ou não possua o header esperado, o método
     * não realiza o abort e retorna false.
     *
     * Quando o abort ocorre, um log é registrado opcionalmente com os dados
     * da exceção capturada.
     *
     * @param Response|null   $response       Resposta HTTP a ser validada para abortar o fluxo.
     * @param Throwable|null  $logThrowable   Exceção opcional para registro em log.
     *
     * @return Response|bool
     *         Retorna a instância de {@see Response} quando o middleware é abortado,
     *         ou false quando o fluxo deve ser interrompido de acordo com o padrão do Lumynus.
     */
    public function abort(?Response $response = null, ?Throwable $logThrowable = null): Response|bool
    {
        if ($response === null) {
            return false;
        }

        if (!in_array(
            'content-type',
            array_map(
                'strtolower',
                array_values((array) $response)
            )
        )) {
            return false;
        }
        $this->logs()->register('Middleware aborted', [
            'middleware' => static::class,
            'exception'  => $logThrowable?->getMessage(),
            'trace'      => $logThrowable?->getTraceAsString(),
        ]);
        return $response;
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
