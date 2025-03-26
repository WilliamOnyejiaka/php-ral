<?php

declare(strict_types=1);

namespace Lib;

class Request
{
    public function json(string $key, $default = null)
    {
        $body = json_decode(file_get_contents("php://input") ?: '{}', true);
        return $body[$key] ?? $default;
    }

    public function args(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function authorization(string $type): ?string
    {
        return match ($type) {
            'email' => $_SERVER['PHP_AUTH_USER'] ?? null,
            'password' => $_SERVER['PHP_AUTH_PW'] ?? null,
            default => null
        };
    }

    public function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    public function set_header(string $name, string $value): void
    {
        header("$name: $value");
    }

    public function locals(string $key, $value = null)
    {
        $_SERVER['locals'] ??= [];
        return $value === null ? ($_SERVER['locals'][$key] ?? null) : ($_SERVER['locals'][$key] = $value);
    }

    public function get_header(string $key): ?string
    {
        return getallheaders()[$key] ?? null;
    }
}
