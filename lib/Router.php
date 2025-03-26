<?php
// testing
declare(strict_types=1);

namespace Lib;

ini_set('display_errors', 1);

use Lib\Response;
use Lib\Request;

class Router extends BaseRouter
{
  private $callback_404;
  private $callback_405;
  private string $uri_path_start;
  private bool   $allow_cors;
  private Request $request;
  private Response $response;
  private array  $origins;

  public function __construct(string $uri_path_start, bool $allow_cors, array $origins = [])
  {
    $this->request       = new Request();
    $this->response      = new Response();
    parent::__construct();
    $this->allow_cors    = $allow_cors;
    $this->uri_path_start = $uri_path_start;
    $this->origins       = $origins;
  }

  public function group(Blueprint $blueprint): Router
  {
    $route_names = [];
    foreach ($blueprint->handlers as $index => $handler) {
      $handler['name']  = $handler['name'] ?? uniqid('route_');
      $route_names[]    = $handler['name'];
      $this->handlers[$index] = $handler;
    }

    if (isset($blueprint->middlewares)) {
      $this->middlewares = array_merge($this->middlewares, $blueprint->middlewares);
    }

    if (isset($blueprint->blueprint_middlewares)) {
      foreach ($blueprint->blueprint_middlewares as $index => $middleware) {
        $this->middlewares[$index] = [
          'routes'    => $route_names,
          'middleware' => $middleware['middleware']
        ];
      }
    }

    return $this;
  }

  private function setOrigins(): void
  {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!empty($this->origins)) {
      if (in_array($origin, $this->origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
      }
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

    if ($_SERVER['REQUEST_METHOD'] === "OPTIONS") {
      header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
      header("HTTP/1.1 200 OK");
      exit();
    }
  }

  public function add_405_callback(callable $callback): void
  {
    $this->callback_405 = $callback;
  }

  private function get_request_path(): string
  {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $paths       = array_filter(explode("/", $request_uri));
    $paths       = array_values($paths);

    $start_index = array_search($this->uri_path_start, $paths);
    if ($start_index === false) {
      throw new \RuntimeException("Starting path not found: {$this->uri_path_start}");
    }

    $requestPath = '';
    for ($i = $start_index + 1; $i < count($paths); $i++) {
      $requestPath .= "/" . $paths[$i];
    }
    return $requestPath ?: '/';
  }

  public function add_404_callback(callable $callback): void
  {
    $this->callback_404 = $callback;
  }

  private function invoke_middleware($callback): void
  {
    try {
      if (is_array($callback) && count($callback) === 2) {
        [$class, $method] = $callback;
        $instance         = new $class();
        $callback         = [$instance, $method];
      }
      call_user_func($callback, $this->request, $this->response);
    } catch (\Exception $e) {
      $this->response->json(['error' => 'Middleware failed: ' . $e->getMessage()], 500);
      exit();
    }
  }

  private function invoke_callback($callback): void
  {
    if (!$callback) {
      $callback = $this->callback_404 ?? fn() => $this->response->json([
        'error'   => true,
        'message' => "The requested URL cannot be found"
      ], 404);
    }

    try {
      if (is_string($callback) && strpos($callback, '::') !== false) {
        [$class, $method] = explode('::', $callback);
        $instance         = new $class();
        $callback         = [$instance, $method];
      }
      call_user_func($callback, $this->request, $this->response);
    } catch (\Exception $e) {
      $this->response->json(['error' => 'Callback failed: ' . $e->getMessage()], 500);
      exit();
    }
  }

  private function invoke_405(): callable
  {
    return $this->callback_405 ?? fn() => $this->response->json([
      'error'   => true,
      'message' => "Method not allowed"
    ], 405);
  }

  public function run(): void
  {
    try {
      $request_path = $this->get_request_path();
      $method       = $_SERVER['REQUEST_METHOD'];
      $callback     = null;
      $matchedHandler = null;

      foreach ($this->handlers as $handler) {
        $handlerMethods = (array)$handler['method'];
        $methods        = array_map('strtoupper', $handlerMethods);

        if (preg_match($handler['pattern'], $request_path, $matches)) {
          if (in_array($method, $methods)) {
            $callback      = $handler['callback'];
            $matchedHandler = $handler;
            // Set parameters in Request object
            foreach ($handler['params'] as $paramName) {
              $this->request->params[$paramName] = $matches[$paramName] ?? null;
            }
            break;
          } else {
            $callback = $this->invoke_405();
          }
        }
      }

      if ($this->allow_cors) {
        $this->activate_cors();
      }

      if ($callback && !empty($this->middlewares) && $matchedHandler) {
        foreach ($this->middlewares as $middleware) {
          $routes = (array)$middleware['routes'];
          if (in_array($matchedHandler['name'] ?? '', $routes)) {
            $this->invoke_middleware($middleware['middleware']);
          }
        }
      }

      $this->invoke_callback($callback);
    } catch (\Exception $e) {
      $this->response->json(['error' => $e->getMessage()], 500);
      exit();
    }
  }
}
