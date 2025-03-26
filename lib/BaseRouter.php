<?php

declare(strict_types=1);

namespace Lib;

ini_set("display_errors", 1);
error_reporting(E_ALL);

class BaseRouter
{
    protected array $handlers = [];
    protected const METHOD_GET = "GET";
    protected const METHOD_POST = "POST";
    protected const METHOD_PUT = "PUT";
    protected const METHOD_PATCH = "PATCH";
    protected const METHOD_DELETE = "DELETE";
    protected string $url_prefix = '';

    public function __construct() {
        
    }

    public function get(string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler(self::METHOD_GET, $path, $callback, $name);
    }

    public function post(string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler(self::METHOD_POST, $path, $callback, $name);
    }

    public function put(string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler(self::METHOD_PUT, $path, $callback, $name);
    }

    public function patch(string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler(self::METHOD_PATCH, $path, $callback, $name);
    }

    public function delete(string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler(self::METHOD_DELETE, $path, $callback, $name);
    }

    public function route(string|array $methods, string $path, callable $callback, ?string $name = null): self
    {
        return $this->add_handler($methods, $path, $callback, $name);
    }

    protected function add_handler(string|array $method, string $path, callable $callback, ?string $name): self
    {
        $index = is_array($method) ? implode(",", $method) : $method;
        $path = rtrim($this->url_prefix . $path, '/');

        $this->handlers[$index . $path] = [
            'path' => $path,
            'method' => $method,
            'callback' => $callback,
            'name' => $name ?? uniqid(),
            'pattern' => $this->convertToRegex($path)
        ];
        return $this;
    }

    protected function convertToRegex(string $path): string
    {
        return '#^' . preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
    }
}
