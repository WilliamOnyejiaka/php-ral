<?php

declare(strict_types=1);

namespace Lib;

class Response
{
  public function send_response(int $code, array $data): void
  {
    http_response_code($code);
    echo json_encode($data);
    exit;
  }

  public function send_error_response(int $code, string $message): void
  {
    $this->send_response($code, [
      'error' => true,
      'message' => $message
    ]);
  }

  public function render(string $htmlPath, array $data = [], string $basePath = "./../static/templates/"): void
  {
    header("Content-Type: text/html");
    extract($data);
    ob_start();
    include_once $basePath . $htmlPath;
    echo ob_get_clean();
    exit;
  }

  public function get_header(string $key): ?string
  {
    return getallheaders()[$key] ?? null;
  }
}
