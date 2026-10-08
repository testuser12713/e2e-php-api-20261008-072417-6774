<?php

declare(strict_types=1);

namespace App\Routing;

use App\Http\Request;
use App\Http\Response;

final class Router
{
    /**
     * @var list<array{method: string, regex: string, params: list<string>, handler: callable}>
     */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $params = [];
        $pattern = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $match) use (&$params): string {
                $params[] = $match[1];

                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '#^' . $pattern . '$#',
            'params' => $params,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] === $method) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? '';
                }

                return ($route['handler'])($request, $params);
            }

            $allowed[$route['method']] = true;
        }

        if ($allowed !== []) {
            $allow = implode(', ', array_keys($allowed));

            return Response::json(
                [
                    'error' => [
                        'code' => 'method_not_allowed',
                        'message' => 'The HTTP method is not allowed for this path.',
                    ],
                ],
                405
            )->withHeader('Allow', $allow);
        }

        return Response::json(
            [
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The requested resource was not found.',
                ],
            ],
            404
        );
    }
}
