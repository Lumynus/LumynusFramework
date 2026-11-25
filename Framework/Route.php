<?php

declare(strict_types=1);

/**
 * @author Weleny Santos <welenysantos@gmail.com>
 * @package Lumynus\Framework
 */

namespace Lumynus\Framework;

use Lumynus\Framework\Config;
use Lumynus\Framework\LumaClasses;
use Lumynus\Framework\Logs;
use Lumynus\Http\HttpRequest;
use Lumynus\Http\HttpResponse;
use Lumynus\Http\Contracts\Request as ContractsRequest;
use Lumynus\Http\Contracts\Response as ContractsResponse;
use Lumynus\Http\HttpException;

/**
 * Class responsible for routing management in Lumynus framework.
 * Enables route registration with parameter and type validation.
 */
final class Route extends LumaClasses
{

    /**
     * Stores all registered routes, organized by HTTP method.
     *
     * @var array
     */
    private static array $routes = [];

    /**
     * Armazena a pilha de middlewares registrados.
     */
    private static array $middlewareStack = [];

    /**
     * Stores dynamic routes.
     *
     * @var array
     */
    private static array $dynamicRoutes = [];

    /**
     * Tracks whether routes have already been loaded into memory.
     */
    private static bool $booted = false;

    /**
     * Indicates whether application is running in CLI (Command Line Interface) mode.
     *
     * When `true`, indicates script is executing via terminal,
     * enabling specific behaviors for command-line environments.
     * When `false`, execution occurs in web (HTTP) environment.
     *
     * @var bool
     */
    private static bool $cliMode = false;

    /**
     * Registers a GET route.
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'show'
     * @return void
     */
    public static function get(string|array $route, string $controller, string $action): void
    {
        self::register('GET', $route, $controller, $action);
    }

    /**
     * Registers a POST route.
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'create'
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return void
     */
    public static function post(string|array $route, string $controller, string $action, bool $noCsrf = false): void
    {
        self::register('POST', $route, $controller, $action, $noCsrf);
    }

    /**
     * Registers a PUT route.
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'update'
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return void
     */
    public static function put(string|array $route, string $controller, string $action, bool $noCsrf = false): void
    {
        self::register('PUT', $route, $controller, $action, $noCsrf);
    }

    /**
     * Registers a DELETE route.
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'delete'
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return void
     */
    public static function delete(string|array $route, string $controller, string $action, bool $noCsrf = false): void
    {
        self::register('DELETE', $route, $controller, $action, $noCsrf);
    }

    /**
     * Registers a PATCH route.
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'update'
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return void
     */
    public static function patch(string|array $route, string $controller, string $action, bool $noCsrf = false): void
    {
        self::register('PATCH', $route, $controller, $action, $noCsrf);
    }

    /**
     * Registers a route matching any HTTP method (GET, POST, PUT, PATCH, DELETE).
     *
     * @param string|array $route @example '/users/{id}[int]'
     * @param string $controller @example 'UserController'
     * @param string $action @example 'update'
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route (except for GET).
     * @return void
     */
    public static function any(string|array $route, string $controller, $action, bool $noCsrf = false): void
    {
        self::register('GET', $route, $controller, $action);
        self::register('POST', $route, $controller, $action, $noCsrf);
        self::register('PUT', $route, $controller, $action, $noCsrf);
        self::register('DELETE', $route, $controller, $action, $noCsrf);
        self::register('PATCH', $route, $controller, $action, $noCsrf);
    }

    /**
     * Registers middlewares applied to routes defined inside the callback.
     *
     * @param string|array $middlewares Middleware class name(s) to apply.
     * @param string|array $actions Action name(s) to execute.
     * @param callable $callback Callback function where routes are defined.
     * @return void
     */
    public static function midd(array|string $middlewares, array|string $actions, callable $callback): void
    {
        $previousStack = self::$middlewareStack;

        $map = [];

        if (is_string($middlewares) && is_string($actions)) {
            $map[] = [
                'midd' => $middlewares,
                'action' => $actions
            ];
        } elseif (is_array($middlewares)) {
            if (is_string($actions)) {
                foreach ($middlewares as $midd) {
                    $map[] = [
                        'midd' => $midd,
                        'action' => $actions
                    ];
                }
            } elseif (is_array($actions)) {
                if (self::l_countStatic($middlewares) !== self::l_countStatic($actions)) {
                    throw new \InvalidArgumentException(
                        "If 'middlewares' and 'actions' are arrays, they must have the same number of elements."
                    );
                }

                foreach ($middlewares as $i => $midd) {
                    $map[] = [
                        'midd' => $midd,
                        'action' => $actions[$i]
                    ];
                }
            }
        } else {
            throw new \InvalidArgumentException("Middlewares and actions must be strings or arrays.");
        }

        self::$middlewareStack = array_merge(self::$middlewareStack, $map);
        try {
            $callback();
        } finally {
            self::$middlewareStack = $previousStack;
        }
    }

    /**
     * Registers a route based on HTTP method.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE).
     * @param string|array $route Route path, optionally containing field definitions.
     * @param string $controller Controller class name.
     * @param string $action Action method on controller.
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return void
     */
    private static function register(string $method, string|array $route, string $controller, string $action, bool $noCsrf = false): void
    {
        if (self::$booted) return;

        if (is_array($route)) {
            foreach ($route as $r) self::register($method, $r, $controller, $action, $noCsrf);
            return;
        }

        $parsed = self::parseRouteConfig($route, $noCsrf);
        $config = [
            'controller'      => $controller,
            'action'          => $action,
            'fieldsPermitted' => $parsed['fields'],
            'middlewares'     => array_values(self::$middlewareStack),
            'api'             => $parsed['api']
        ];

        if ($parsed['isDynamic']) {
            self::$dynamicRoutes[strtoupper($method)][$parsed['regex']] = $config;
        } else {
            self::$routes[strtoupper($method)][$parsed['cleanRoute']] = $config;
        }
    }

    /**
     * Parses route string and extracts allowed fields and their types.
     *
     * @param string $route Route with or without field definitions in brackets.
     * @param bool $noCsrf Whether CSRF verification should be disabled for this route.
     * @return array Array containing [clean route, allowed fields].
     */
    private static function parseRouteConfig(string $route, bool $noCsrf = false): array
    {
        $isApi = false;
        $fieldsPermitted = [];
        $queryConfigString = '';

        // 1. Remove leading slash if present
        if (str_starts_with($route, '/')) {
            $route = substr($route, 1);
        }

        // 2. Check and remove trailing {api} flag
        if (str_ends_with($route, '{api}')) {
            $isApi = true;
            $route = substr($route, 0, -5);
        }

        // 3. If route is flagged as noCsrf, treat as API
        if ($noCsrf) {
            $isApi = true;
        }

        // 4. Separate physical route from Query String configuration (?)
        if (str_contains($route, '?')) {
            // Divide em [0] => 'meu/{id}[int]', [1] => '[string nome][int *]'
            [$routePath, $queryConfigString] = explode('?', $route, 2);
            $route = rtrim($routePath, '/'); // Remove trailing slash before ? if present
        }

        // 5. Process Query String configurations: [type name]
        if (!empty($queryConfigString)) {
            // Regex to capture: [type name] or [type *]
            // Ex: [string va] -> Match 1: string, Match 2: va
            preg_match_all('/\[([a-zA-Z]+)\s+([a-zA-Z0-9_*]+)\]/', $queryConfigString, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $type = $match[1]; // ex: string
                $name = $match[2]; // ex: va (ou *)

                // Save to allowed fields array
                $fieldsPermitted[$name] = $type;
            }
        }

        // 6. If no curly brackets {}, route is static (may still have query string processed above)
        if (!str_contains($route, '{')) {
            return [
                'isDynamic' => false,
                'cleanRoute' => $route,
                'regex' => null,
                'fields' => $fieldsPermitted, // Inclui os campos da QueryString
                'api' => $isApi
            ];
        }

        // 7. Dynamic route processing (path)
        $safeRoute = preg_quote($route, '~');
        $pattern = '/\\\\\{(\w+)\\\\\}(?:\\\\\[(\w+)\\\\\])?/';

        $regexRoute = preg_replace_callback($pattern, function ($matches) use (&$fieldsPermitted) {
            $paramName = $matches[1];
            $paramType = $matches[2] ?? '*';

            $fieldsPermitted[$paramName] = $paramType;

            return '(?P<' . $paramName . '>[^/]+)';
        }, $safeRoute);

        $finalRegex = '~^' . $regexRoute . '$~';

        return [
            'isDynamic' => true,
            'cleanRoute' => $route,
            'regex' => $finalRegex,
            'fields' => $fieldsPermitted,
            'api' => $isApi
        ];
    }

    /**
     * Clears internal state.
     * Useful for unit testing or hot-reloading routes.
     */
    public static function clear(): void
    {
        if (self::$booted && !self::$cliMode) {
            Logs::register('Warning', ['msg' => 'Attempted to clear routes during runtime. Operation blocked.']);
            return;
        }
        self::$routes = [];
        self::$dynamicRoutes = [];
        self::$middlewareStack = [];
        self::$booted = false;
    }

    /**
     * Enables CLI (Command Line Interface) execution mode.
     *
     * Sets application execution environment to command line,
     * enabling terminal-specific flows and behaviors.
     *
     * @return void
     */
    public static function enableCliMode(): void
    {
        self::$cliMode = true;
    }

    /**
     * Requires all router files available in src/Routers directory.
     * Used to load routes defined across separated router files.
     *
     * @return void
     */
    private static function requireRouters()
    {
        $projetoRoot = Config::projectPath();

        if (!is_dir($projetoRoot)) {
            throw new HttpException('Route files not found', 500);
        }

        $routerFiles = glob(
            $projetoRoot .
                DIRECTORY_SEPARATOR .
                'src' .
                DIRECTORY_SEPARATOR .
                'routers' .
                DIRECTORY_SEPARATOR .
                '*.php'
        );

        ob_start();
        foreach ($routerFiles as $file) {
            if (is_file($file) && !str_contains($file, '..')) {
                require_once $file;
            }
        }
        ob_end_clean();
    }

    /**
     * Validates received parameters against permitted route fields.
     *
     * @param array $params Parameters received by request.
     * @param mixed $fieldsPermitted Permitted fields (array or '*').
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private static function validateParams(array $params, mixed $fieldsPermitted): array
    {
        if ($fieldsPermitted === '*') {
            return ['valid' => true, 'error' => '...'];
        }

        foreach ($params as $key => $value) {
            $expectedType = null;

            if (array_key_exists($key, $fieldsPermitted)) {
                $expectedType = $fieldsPermitted[$key];
            } elseif (isset($fieldsPermitted['*'])) {
                $expectedType = $fieldsPermitted['*'];
            }

            if ($expectedType === null) {
                return ['valid' => false, 'error' => "Field '$key' is not permitted."];
            }

            if (!self::validateType($value, $expectedType)) {
                return ['valid' => false, 'error' => "Invalid type for '$key'. Expected $expectedType."];
            }
        }

        return ['valid' => true, 'error' => '...'];
    }

    /**
     * Validates whether a value matches the expected type.
     *
     * @param mixed $value Value to validate.
     * @param string $type Expected type (string, int, float, bool, id).
     * @return bool
     */
    private static function validateType($value, string $type): bool
    {
        return match ($type) {
            '*'      => true,
            'string' => is_string($value),
            'int'    => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'float'  => filter_var($value, FILTER_VALIDATE_FLOAT) !== false,
            'array' => is_array($value),
            'bool'   => is_bool($value)
                || (is_string($value) && in_array(strtolower($value), ['1', '0', 'true', 'false'], true)),
            default  => false,
        };
    }

    /**
     * Retrieves request headers with cross-environment compatibility.
     *
     * @return array Associative array of request headers.
     */
    private static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace(' ', '-', ucwords(strtolower(substr($key, 5)), ''));
                $headers[$header] = $value;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $header = str_replace(' ', '-', ucwords(strtolower($key), ''));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Loads routes from cache file if present and up-to-date.
     * Checks file modification timestamps to invalidate cache automatically.
     *
     * @return bool
     */
    private static function loadRoutesFromCache(): bool
    {
        $basePath = Config::projectPath();
        $cachePathRelative = Config::getApplicationConfig()['path']['cache'] . 'routers' . DIRECTORY_SEPARATOR;
        $cacheFile = $basePath . $cachePathRelative . 'routes.cache.php';

        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);

        $routerPattern = $basePath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'routers' . DIRECTORY_SEPARATOR . '*.php';
        $routerFiles = glob($routerPattern);

        if ($routerFiles === false) {
            return false;
        }

        foreach ($routerFiles as $file) {
            if (is_file($file) && filemtime($file) > $cacheTime) {
                return false;
            }
        }

        foreach (['config.ini', 'application.json'] as $configurationFile) {
            $path = $basePath . DIRECTORY_SEPARATOR . $configurationFile;
            if (is_file($path) && filemtime($path) > $cacheTime) {
                return false;
            }
        }

        $cachedData = require $cacheFile;

        if (isset($cachedData['static']) || isset($cachedData['dynamic'])) {
            self::$routes = $cachedData['static'] ?? [];
            self::$dynamicRoutes = $cachedData['dynamic'] ?? [];
        } else {
            return false;
        }

        return true;
    }

    /**
     * Saves registered routes into a cache file.
     * Used to optimize route loading on subsequent executions.
     *
     * @return void
     */
    private static function cacheRoutes(): void
    {
        $basePath = Config::projectPath();
        $cacheFile = $basePath . Config::getApplicationConfig()['path']['cache'] . 'routers' . DIRECTORY_SEPARATOR . 'routes.cache.php';

        $dataToCache = [
            'static'  => self::$routes,
            'dynamic' => self::$dynamicRoutes
        ];

        $export = var_export($dataToCache, true);
        $content = "<?php\n\nreturn {$export};";

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        $tmpFile = $cacheFile . '.' . uniqid((string)mt_rand(), true) . '.tmp';

        if (file_put_contents($tmpFile, $content, LOCK_EX) !== false) {

            if (PHP_OS_FAMILY === 'Windows' && file_exists($cacheFile)) {
                @unlink($cacheFile);
            }

            if (!@rename($tmpFile, $cacheFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Resolves controller instance ensuring isolation.
     * In the future, this will be integrated with the Lumynus DI Container.
     */
    private static function resolveController(string $controllerClass): object
    {
        if (!class_exists($controllerClass)) {
            throw new HttpException("Controller $controllerClass not found", 500);
        }
        return new $controllerClass();
    }

    /**
     * Initializes application boot lifecycle.
     *
     * Executes route loading only once during application lifecycle.
     * If valid route cache exists, it is utilized.
     * Otherwise, route files are loaded manually and subsequently cached.
     *
     * Upon completion, flags application as booted and resets middleware stack.
     *
     * @return void
     */
    public static function boot(): void
    {
        if (self::$booted) return;

        if (!self::loadRoutesFromCache()) {
            self::requireRouters();
            self::cacheRoutes();
        }

        self::$booted = true;
        self::$middlewareStack = [];
    }

    /**
     * Resolves and normalizes HTTP request data from `$server` array.
     *
     * Extracts request method and URI, automatically stripping:
     * - Application base path
     * - Script filename (e.g. index.php)
     *
     * The result is a clean route ready for router dispatching.
     *
     * @param array $server Array representing $_SERVER.
     *
     * @return array{
     *     0: string, // normalized route (without leading/trailing slashes)
     *     1: string, // HTTP method (GET, POST, etc.)
     *     2: string  // original URI
     * }
     */
    public static function resolveRequestRoute(array $server): array
    {

        $method = $server['REQUEST_METHOD'] ?? 'GET';

        // Allows HTTP Method Spoofing
        if ($method === 'POST') {
            $override = $post['_method']
                ?? $server['HTTP_X_HTTP_METHOD_OVERRIDE']
                ?? null;

            if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        $uri    = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $script = str_replace('\\', '/', $server['SCRIPT_NAME'] ?? '');
        $base   = rtrim(dirname($script), '/');

        if ($base !== '' && $base !== '.' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $index = basename($script);
        if (str_starts_with($uri, '/' . $index)) {
            $uri = substr($uri, strlen('/' . $index));
        }

        return [trim($uri, '/'), $method, $uri];
    }

    /**
     * Matches a route based on HTTP method and path.
     *
     * First checks registered static routes. If not found,
     * iterates through dynamic regex routes for the method, automatically
     * extracting named parameters upon matching.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param string $route  Normalized route path (without leading/trailing slashes).
     *
     * @return array{
     *     0: array|null,
     *     1: array<string, string>
     * }
     */
    private static function matchRoute(string $method, string $route): array
    {

        $routeConfig = self::$routes[$method][$route] ?? null;
        $routeParams = [];

        if (!$routeConfig && isset(self::$dynamicRoutes[$method])) {
            foreach (self::$dynamicRoutes[$method] as $regex => $config) {
                if (preg_match($regex, $route, $matches)) {
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) $routeParams[$key] = $value;
                    }
                    $routeConfig = $config;
                    break;
                }
            }
        }

        return [$routeConfig, $routeParams];
    }

    /**
     * Executes middleware stack associated with current route.
     *
     * For each configured middleware:
     * - Instantiates defined class.
     * - Executes specified action, injecting Request, Response, and route parameters.
     *
     * Flow control rules:
     * - On exception, error is logged and a 500 response is emitted.
     * - If middleware returns `false`, execution halts with 403 response.
     * - If middleware returns a ContractsResponse instance, it is immediately dispatched
     *   and request lifecycle terminates.
     *
     * If no middleware interrupts flow, execution proceeds to controller dispatcher.
     *
     * @param array             $routeConfig Route configuration containing middleware list.
     * @param ContractsRequest  $request     Current request object.
     * @param ContractsResponse $response    Current response object.
     * @param array             $params      Extracted route parameters.
     *
     * @return bool|ContractsResponse
     */
    private static function handleMiddlewares(
        $routeConfig,
        ContractsRequest $request,
        ContractsResponse $response,
        $params
    ): ContractsResponse|bool {
        foreach ($routeConfig['middlewares'] ?? [] as $midd) {
            $instance = new $midd['midd']();
            try {
                $result = $instance->{$midd['action']}($request, $response, $params);
            } catch (\Throwable $e) {
                Logs::register("Middleware {$midd['midd']} Error", ['msg' => $e->getMessage()]);
                throw new HttpException('Internal server error', 500);
            }

            if ($result === false) {
                Logs::register(
                    "Process interrupted by middleware {$midd['midd']}",
                    ['msg' => "The middleware interrupted the process through the {$midd['action']} method."]
                );
                throw new HttpException('Forbidden', 403);
            }

            if ($result instanceof ContractsResponse) {
                return $result;
            }
        }
        return true;
    }

    /**
     * Dispatches controller associated with matched route.
     *
     * Resolves controller instance, identifies configured action method,
     * and executes invocation via Reflection for automatic dependency injection
     * based on parameter types.
     *
     * Injection rules:
     * - HttpRequest / ContractsRequest   → injects request object
     * - HttpResponse / ContractsResponse  → injects response object
     * - Other parameters                 → injects route parameters array
     *
     * Return value handling:
     * - If returning ContractsResponse instance, it is dispatched.
     * - If returning string or number, sent as HTML response.
     * - Otherwise, default response is dispatched.
     *
     * On exception, error is logged and a 500 (Internal Server Error) response is emitted.
     *
     * @param array             $routeConfig Route configuration (controller and action).
     * @param ContractsRequest  $request     Current request object.
     * @param ContractsResponse $response    Current response object.
     * @param array             $params      Extracted route parameters.
     *
     * @return ContractsResponse
     */
    private static function dispatchController($routeConfig, ContractsRequest $request, ContractsResponse $response, $params): ContractsResponse
    {
        $controller = self::resolveController($routeConfig['controller']);
        $methodName = $routeConfig['action'];

        try {
            $reflection = new \ReflectionMethod($controller, $methodName);
            $args = [];
            foreach ($reflection->getParameters() as $param) {
                $type = ($param->getType() instanceof \ReflectionNamedType) ? $param->getType()->getName() : null;
                match ($type) {
                    HttpRequest::class, ContractsRequest::class => $args[] = $request,
                    HttpResponse::class, ContractsResponse::class => $args[] = $response,
                    default => $args[] = $params
                };
            }

            $result = $controller->{$methodName}(...$args);

            if ($result instanceof ContractsResponse) {
                return $result;
            } elseif (is_string($result) || is_numeric($result)) {
                return $response->html((string) $result);
            } elseif (is_array($result)) {
                return $response->json((array) $result);
            } else {
                return $response;
            }
        } catch (\Throwable $e) {
            Logs::register("Controller " . get_class($controller) . "::{$methodName} Error", ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw new HttpException('Internal server error', 500);
        }
    }

    /**
     * Starts application routing.
     * Reads request URI, matches static or dynamic routes, validates and executes.
     *
     * @return ContractsResponse
     */
    public static function start(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        ?array $headers = null,
        ?string $rawContent = null
    ): ContractsResponse {

        self::$middlewareStack = [];

        self::boot();

        $server  = $server  ?? $_SERVER;
        $get     = $get     ?? $_GET;
        $post    = $post    ?? $_POST;
        $files   = $files   ?? $_FILES;
        $headers = $headers ?? self::getRequestHeaders();
        $headers = array_change_key_case($headers, CASE_UPPER);

        [$route, $method, $uri] = self::resolveRequestRoute($server);

        [$routeConfig, $routeParams] = self::matchRoute($method, $route);

        if (!$routeConfig) {
            throw new HttpException("Route {$route} not found", 404);
        }

        $params = array_merge($get, $routeParams);

        // INSTANTIATION
        $request = new HttpRequest($method, $uri, $params, $post, $headers, $files, $server, $rawContent);
        $response = new HttpResponse();
        $input = $request->getParsedBody() ?? [];

        // Parameter Validation
        if (!self::validateParams($params, $routeConfig['fieldsPermitted'])['valid']) {
            Logs::register("Validation Params in route {$route}", ['params' => $params, 'allowed' => $routeConfig['fieldsPermitted']]);
            throw new HttpException('Forbidden', 403);
        }

        // CSRF (Usando $post e $server locais)
        $config = Config::getApplicationConfig()['security']['csrf'];
        if (!($routeConfig['api'] ?? false) && $config['enabled'] && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $tokenName = $config['tokenName'];
            $token =
                $headers['X-CSRF-TOKEN']
                ?? $headers['X-XSRF-TOKEN']
                ?? $post[$tokenName]
                ?? $server['HTTP_X_CSRF_TOKEN']
                ?? $input[$tokenName]
                ?? $headers[$tokenName]
                ?? null;

            if (!$token || !CSRF::isValidToken($token)) {
                Logs::register("CSRF Token Mismatch in route {$route}", ['token' => $token]);
                throw new HttpException('Page Expired', 419);
            }
        }

        $customizeParamsPosts = [
            'GET' => $params,
            'POST' => $post,
            'INPUT' => $input,
            'FILE' => $files,
            'HEADER' => $headers
        ];

        // MIDDLEWARES
        $middlewareResult = self::handleMiddlewares($routeConfig, $request, $response, $customizeParamsPosts);

        if ($middlewareResult instanceof ContractsResponse) {
            return $middlewareResult;
        }

        // CONTROLLER
        $response =  self::dispatchController($routeConfig, $request, $response, $customizeParamsPosts);
        if (!$response instanceof ContractsResponse) {
            throw new \LogicException('Controller must return a valid Response instance.');
        }
        return $response;
    }

    /**
     * Returns all routes registered in the system.
     *
     * @return array
     */
    public static function listRoutes(): array
    {
        return self::$routes;
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
