<?php

class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(array $server): void
    {
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $path = norm_path($server['REQUEST_URI'] ?? '/');
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            $this->sendJson(['error' => 'Not Found'], 404);
            return;
        }

        try {
            $result = is_callable($handler) ? $handler() : $handler;
            $this->sendJson($result);
        } catch (Throwable) {
            $this->sendJson(['error' => 'Server error'], 500);
        }
    }

    private function sendJson(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}