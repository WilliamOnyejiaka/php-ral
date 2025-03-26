<?php
declare(strict_types=1);

namespace Lib;

ini_set("display_errors", 1);

class BaseRouter
{
    protected array  $handlers     = [];
    protected array  $middlewares  = [];
    protected string $url_prefix   = '';

    protected const METHOD_GET    = "GET";
    protected const METHOD_POST   = "POST";
    protected const METHOD_PUT    = "PUT";
    protected const METHOD_PATCH  = "PATCH";
    protected const METHOD_DELETE = "DELETE";

    public function __construct()
    {
    }

    public function get(string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler(self::METHOD_GET, $path, $callback, $name);
        return $this;
    }

    public function post(string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler(self::METHOD_POST, $path, $callback, $name);
        return $this;
    }

    public function put(string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler(self::METHOD_PUT, $path, $callback, $name);
        return $this;
    }

    public function patch(string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler(self::METHOD_PATCH, $path, $callback, $name);
        return $this;
    }

    public function delete(string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler(self::METHOD_DELETE, $path, $callback, $name);
        return $this;
    }

    public function route(string|array $methods, string $path, $callback, string $name = null): BaseRouter
    {
        $this->add_handler($methods, $path, $callback, $name);
        return $this;
    }

    private function add_handler(string|array $method, string $path, $callback, ?string $name): void
    {
        if (!is_callable($callback) && !is_string($callback) && !is_array($callback)) {
            throw new \InvalidArgumentException('Callback must be callable, string, or array');
        }

        $index    = is_array($method) ? implode(",", $method) : $method;
        $fullPath = $this->url_prefix . $path;

        if (isset($this->handlers[$index . $fullPath])) {
            throw new \RuntimeException("Route already exists: {$index} {$fullPath}");
        }

        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $pattern = "#^" . $pattern . "$#";

        $this->handlers[$index . $fullPath] = [
            'path'     => $fullPath,
            'method'   => $method,
            'callback' => $callback,
            'name'     => $name ?? uniqid('route_'),
            'pattern'  => $pattern,
            'params'   => $this->extractParams($fullPath)
        ];
    }

    private function extractParams(string $path): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
        return $matches[1] ?? [];
    }

    public function middleware($middleware, array|string $routes): BaseRouter
    {
        $this->add_middleware($middleware, $routes);
        return $this;
    }

    private function add_middleware($middleware, array|string $routes): void
    {
        if (!is_callable($middleware) && !is_string($middleware) && !is_array($middleware)) {
            throw new \InvalidArgumentException('Middleware must be callable, string, or array');
        }

        $index                    = is_array($routes) ? implode(",", $routes) : $routes;
        $this->middlewares[uniqid('mw_')] = [
            'routes'    => $routes,
            'middleware' => $middleware
        ];
    }
}