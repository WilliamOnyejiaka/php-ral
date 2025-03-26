<?php
declare(strict_types=1);
namespace Lib;

ini_set('display_errors', 1);

use Lib\Response;
use Lib\Request;
use Lib\Blueprint;
use Lib\BaseRouter;

class Router extends BaseRouter
{

  private $callback_404;
  private $callback_405;
  private string $uri_path_start;
  private $allow_cors;

  private Request $request;
  private Response $response;

  private array $origins;

  public function __construct($uri_path_start, $allow_cors, array $origins = [])
  {
    $this->request = new Request();
    $this->response = new Response();
    parent::__construct();
    $this->allow_cors = $allow_cors;
    $this->uri_path_start = $uri_path_start;
    $this->origins = $origins;
  }

  public function group(Blueprint $blueprint): Router
  {
    $route_names = [];
    foreach ($blueprint->handlers as $index => $handler) {
      if (isset($blueprint->blueprint_middlewares)) {
        if (!isset($handler['name'])) {
          $handler['name'] = rand(1, 20);
        }
        array_push($route_names, $handler['name']);
      }
      $this->handlers[$index] = $handler;
    }

    if (isset($blueprint->middlewares)) {
      foreach ($blueprint->middlewares as $index => $middleware) {
        $this->middlewares[$index] = $middleware;
      }
    }

    $blueprint_middlewares = [];

    if (isset($blueprint->blueprint_middlewares)) {
      foreach ($blueprint->blueprint_middlewares as $index => $middleware) {
        $blueprint_middlewares[$index] = $middleware;
        $blueprint_middlewares[$index]['routes'] = $route_names;
      }

      $this->middlewares = array_merge($blueprint_middlewares, $this->middlewares);
    }

    return $this;
  }

  private function setOrigins()
  {
    if (!empty($this->origins)) {
      $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

      if (in_array($origin, $this->origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
      }
    } else {
      header('Access-Control-Allow-Origin: *');
    }
  }

  private function activate_cors()
  {
    $this->setOrigins();
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json');


    $method = $_SERVER['REQUEST_METHOD'];
    if ($method == "OPTIONS") {
      $this->setOrigins();
      header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method,Access-Control-Request-Headers, Authorization");
      header("HTTP/1.1 200 OK");
      die();
    }
  }

  public function add_405_callback($callback)
  {
    $this->callback_405 = $callback;
  }

  private function get_request_path(): string
  {
    $request_uri = parse_url($_SERVER['REQUEST_URI']);
    $paths = explode("/", $request_uri['path']);
    $requestPath = "";
    $start_index = null;
    for ($i = 0; $i < count($paths); $i++) {
      if ($paths[$i] === $this->uri_path_start) {
        $start_index = $i + 1;
        break;
      }
    }

    if (!$start_index) {
      http_response_code(404);
      echo json_encode([
        'error' => true,
        'message' => "starting path not found"
      ]);
      exit();
    }
    for ($i = $start_index; $i < count($paths); $i++) {
      $requestPath = $requestPath . "/" . $paths[$i];
    }
    return $requestPath;
  }

  public function add_404_callback($callback)
  {
    $this->callback_404 = $callback;
  }

  private function invoke_middleware($callback)
  {
    if (is_array($callback)) {
      $classname = $callback[0];
      $class = new $classname;

      $method = $callback[1];
      $callback = [$class, $method];
    }

    $response = new Response();
    $request = new Request();
    header('Content-Type: application/json');
    call_user_func_array($callback, [$request, $response]);
  }

  private function invoke_callback($callback)
  {
    if (!$callback) {
      header("HTTP/1.0 404 Not Found");
      if (!empty($this->callback_404)) {
        $callback = $this->callback_404;
      } else {
        $callback = function () {
          http_response_code(404);
          echo json_encode([
            'error' => true,
            'message' => "the requested url cannot be found"
          ]);
        };
      }
    }
    if (is_string($callback)) {
      $parts = explode('::', $callback);
      if (is_array($parts)) {
        $classname = array_shift($parts);
        $class = new $classname;

        $method = array_shift($parts);
        $callback = [$class, $method];
      }
    }

    $this->public_controller($callback);
  }

  public function public_controller($callback): void
  {
    header('Content-Type: application/json');
    $callback($this->request, $this->response);
  }


  private function invoke_405()
  {
    if (!empty($this->callback_405)) {
      return $this->callback_405;
    } else {
      return function () {
        http_response_code(405);
        echo json_encode([
          'error' => true,
          'message' => "method not allowed"
        ]);
      };
    }
  }

  public function run(): void
  {
    $request_path = $this->get_request_path();
    $callback = null;
    $method = $_SERVER['REQUEST_METHOD'];

    foreach ($this->handlers as $handler) {
      if (is_array($handler['method'])) {
        $methods = [];

        foreach ($handler['method'] as $handler_method) {
          array_push($methods, strtoupper($handler_method));
        }
        $handler['method'] = $methods;

        if (in_array($method, $handler['method']) && $handler['path'] === $request_path) {
          $callback = $handler['callback'];
          break;
        }

        if (!in_array($method, $handler['method']) && $handler['path'] === $request_path) {
          $callback = $this->invoke_405();
        }
      }

      if ($method === $handler['method'] && $handler['path'] === $request_path) {
        $callback = $handler['callback'];
        break;
      }

      if ($handler['method'] !== $method && $handler['path'] === $request_path) {
        $callback = $this->invoke_405();
      }
    }

    ($this->allow_cors && $this->activate_cors());

    if (isset($this->middlewares)) {
      foreach ($this->middlewares as $middleware) {
        $routes = $middleware['routes'];
        if (is_array($routes)) {
          if (in_array($handler['name'], $middleware['routes'])) {
            $this->invoke_middleware($middleware['middleware']);
          }
        } else {
          if ($handler['name'] == $middleware['routes']) {
            $this->invoke_middleware($middleware['middleware']);
          }
        }

      }
    }

    $this->invoke_callback($callback);
  }
}