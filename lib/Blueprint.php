<?php
// testing

declare(strict_types=1);

namespace Lib;

ini_set("display_errors", 1);

class Blueprint extends BaseRouter
{
    public array $blueprint_middlewares = [];

    public function __construct(string $url_prefix)
    {
        if (!preg_match('/^\/[a-zA-Z0-9\/]*$/', $url_prefix)) {
            throw new \InvalidArgumentException('URL prefix must start with / and contain only alphanumeric characters and slashes');
        }
        parent::__construct();
        $this->url_prefix = $url_prefix;
    }

    public function __get($property)
    {
        return property_exists($this, $property) ? $this->$property : null;
    }

    public function use(...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (!is_callable($middleware) && !is_string($middleware) && !is_array($middleware)) {
                throw new \InvalidArgumentException('Middleware must be callable, string, or array');
            }
            $this->add_blueprint_middlewares($middleware);
        }
    }

    private function add_blueprint_middlewares($middleware): void
    {
        $this->blueprint_middlewares[uniqid('bp_mw_')] = [
            'routes'    => [],
            'middleware' => $middleware
        ];
    }
}
