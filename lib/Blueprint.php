<?php
declare(strict_types=1);

namespace Lib;

use Lib\BaseRouter;

ini_set("display_errors", 1);

class Blueprint extends BaseRouter
{
    public array $blueprint_middlewares;

    public function __construct(string $url_prefix)
    {
        parent::__construct();
        $this->url_prefix = $url_prefix;
    }

    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        return null;
    }

    public function use ($middleware)
    {
        $this->add_blueprint_middlewares($middleware);
    }

    private function add_blueprint_middlewares($middleware)
    {
        $this->blueprint_middlewares[rand(1, 20) . ""] = [
            'routes' => "",
            'middleware' => $middleware
        ];
    }
}