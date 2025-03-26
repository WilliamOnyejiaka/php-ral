<?php

declare(strict_types=1);

namespace Lib;

class Blueprint extends BaseRouter
{
    public array $blueprint_middlewares = [];

    public function __construct(string $url_prefix)
    {
        parent::__construct();
        $this->url_prefix = rtrim($url_prefix, '/');
    }

    public function use(callable $middleware, array|string $routes = []): self
    {
        $routes = (array)$routes;
        $this->blueprint_middlewares[] = [
            'middleware' => $middleware,
            'routes' => array_map(fn($route) => [
                'path' => rtrim($route, '/'),
                'pattern' => $this->convertToRegex($route)
            ], $routes)
        ];
        return $this;
    }
}
