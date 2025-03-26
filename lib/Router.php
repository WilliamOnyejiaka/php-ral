<?php

declare(strict_types=1);

namespace Lib;

use Exception;

class Router extends BaseRouter
{
  private $callback_404;
  private $callback_405;
  private string $uri_path_start;
  private bool $allow_cors;
  private Request $request;
  private Response $response;
  private array $origins;
  private array $middlewareStack = [];

  public function __construct(string $uri_path_start, bool $allow_cors, array $origins = [])
  {
    parent::__construct();
    $this->request = new Request();
    $this->response = new Response();
    $this->uri_path_start = $uri_path_start;
    $this->allow_cors = $allow_cors;
    $this->origins = $origins;
  }

  public function group(Blueprint $blueprint): self
  {
    $this->handlers = array_merge($this->handlers, $blueprint->handlers);
    if (!empty($blueprint->blueprint_middlewares)) {
      $this->middlewareStack = array_merge($this->middlewareStack, $blueprint->blueprint_middlewares);
    }
    return $this;
  }

  public function middleware(callable $middleware, array|string $routes): self
  {
    $routes = (array)$routes;
    $this->middlewareStack[] = [
      'middleware' => $middleware,
      'routes' => array_map(fn($route) => [
        'path' => rtrim($route, '/'),
        'pattern' => $this->convertToRegex($route)
      ], $routes)
    ];
    return $this;
  }

  private function setOrigins(): void
  {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($this->origins) && in_array($origin, $this->origins)) {
      header('Access-Control-Allow-Origin: ' . $origin);
    } else {
      header('Access-Control-Allow-Origin: *');
    }
  }

  private function activate_cors(): void
  {
    $this->setOrigins();
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
      header("HTTP/1.1 200 OK");
      exit;
    }
  }

  public function add_404_callback(callable $callback): void
  {
    $this->callback_404 = $callback;
  }

  public function add_405_callback(callable $callback): void
  {
    $this->callback_405 = $callback;
  }

  private function get_request_path(): string
  {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $paths = array_filter(explode('/', $request_uri));
    $start_index = array_search($this->uri_path_start, $paths);

    if ($start_index === false) {
      throw new Exception("Starting path not found", 404);
    }

    return '/' . implode('/', array_slice($paths, $start_index + 1));
  }

  private function invoke_callback(?callable $callback, array $params = []): void
  {
    try {
      if (!$callback) {
        $callback = $this->callback_404 ?? fn() => $this->response->send_error_response(404, "Not found");
      }
      call_user_func($callback, $this->request, $this->response, $params);
    } catch (Exception $e) {
      $this->response->send_error_response(500, "Callback error: " . $e->getMessage());
    }
  }

  private function invoke_middleware_stack(callable $callback, array $params, string $request_path): void
  {
    $stack = array_filter($this->middlewareStack, function ($mw) use ($request_path) {
      if (empty($mw['routes'])) {
        return true; // Global middleware
      }
      foreach ($mw['routes'] as $route) {
        if (preg_match($route['pattern'], $request_path)) {
          return true;
        }
      }
      return false;
    });

    $index = 0;
    $next = function () use (&$index, $stack, $callback, $params, &$next) {
      if ($index >= count($stack)) {
        $this->invoke_callback($callback, $params);
        return;
      }

      $middleware = $stack[array_keys($stack)[$index]]['middleware'];
      $index++;

      try {
        call_user_func($middleware, $this->request, $this->response, $next);
      } catch (Exception $e) {
        $this->response->send_error_response(500, "Middleware error: " . $e->getMessage());
      }
    };

    $next();
  }

  public function run(): void
  {
    try {
      if ($this->allow_cors) {
        $this->activate_cors();
      }

      $request_path = $this->get_request_path();
      $method = $_SERVER['REQUEST_METHOD'];
      $callback = null;
      $params = [];

      foreach ($this->handlers as $handler) {
        $methods = (array)$handler['method'];
        if (preg_match($handler['pattern'], $request_path, $matches)) {
          if (!in_array($method, $methods)) {
            $callback = $this->callback_405 ?? fn() => $this->response->send_error_response(405, "Method not allowed");
            break;
          }
          $callback = $handler['callback'];
          $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
          break;
        }
      }

      $this->invoke_middleware_stack($callback, $params, $request_path);
    } catch (Exception $e) {
      $this->response->send_error_response($e->getCode() ?: 500, $e->getMessage());
    }
  }
}
