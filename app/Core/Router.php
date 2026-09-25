<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador com parâmetros tipados ({id:\d+}), grupos com prefixo e middlewares.
 * Middleware: "auth", "guest", "admin".
 */
final class Router
{
    private array $routes = [];
    private array $groupStack = [];

    private const MIDDLEWARE = [
        'auth' => \App\Middleware\Authenticate::class,
        'guest' => \App\Middleware\RedirectIfAuthenticated::class,
        'admin' => \App\Middleware\RequireAdmin::class,
    ];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $this->groupStack[] = ['prefix' => $prefix, 'middleware' => $middleware];
        $routes($this);
        array_pop($this->groupStack);
    }

    private function add(array $methods, string $path, array $handler, array $middleware): void
    {
        $prefix = '';
        $groupMiddleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }
        $full = '/' . trim($prefix . $path, '/');
        // Aceita quantificadores dentro do padrão do parâmetro: {nr:\d{1,2}}, {number:DF\d{6}}.
        $regex = preg_replace_callback(
            '#\{(\w+)(?::((?:[^{}]|\{[^{}]*\})+))?\}#',
            static fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $full
        );
        $this->routes[] = [
            'methods' => $methods,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            if (!in_array($request->method, $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $request->setParams(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            return $this->run($request, $route);
        }
        if ($allowed) {
            throw new HttpException(405);
        }
        throw new HttpException(404);
    }

    private function run(Request $request, array $route): Response
    {
        [$class, $method] = $route['handler'];
        $core = static function (Request $req) use ($class, $method): Response {
            $controller = new $class($req);
            $args = array_values(array_map(
                static fn ($v) => ctype_digit($v) ? (int) $v : $v,
                array_filter($req->params(), static fn ($v) => $v !== '')
            ));
            $result = $controller->$method(...$args);
            return $result instanceof Response ? $result : Response::html((string) $result);
        };

        // Monta a cadeia de middlewares de fora para dentro.
        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            static function (callable $next, string $definition): callable {
                [$name, $param] = array_pad(explode(':', $definition, 2), 2, null);
                $class = self::MIDDLEWARE[$name] ?? throw new \LogicException("Middleware desconhecido: $name");
                return static fn (Request $req): Response => (new $class())->handle($req, $next, $param);
            },
            $core
        );

        return $pipeline($request);
    }
}
