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
        [$handler, $params] = $this->resolve($method, $path);

        if ($handler === null) {
            $this->sendJson(['error' => 'Not Found'], 404);
            return;
        }

        try {
            $result = is_callable($handler)
                ? call_user_func_array($handler, array_values($params))
                : $handler;
            $this->sendJson($result);
        } catch (ApiException $error) {
            $this->sendJson(['error' => $error->getMessage()], $error->statusCode);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $this->sendJson(['error' => 'Server error'], 500);
        }
    }

    private function resolve(string $method, string $path): array
    {
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $parts = preg_split('~(\{[A-Za-z_][A-Za-z0-9_]*\})~', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
            $regex = '';
            $parameterNames = [];

            foreach ($parts as $part) {
                if (preg_match('~^\{([A-Za-z_][A-Za-z0-9_]*)\}$~', $part, $match)) {
                    $parameterNames[] = $match[1];
                    $regex .= '([^/]+)';
                } else {
                    $regex .= preg_quote($part, '~');
                }
            }

            if (preg_match('~^' . $regex . '$~', $path, $matches)) {
                $params = [];
                foreach ($parameterNames as $index => $name) {
                    $params[$name] = $matches[$index + 1];
                }
                return [$handler, $params];
            }
        }

        return [null, []];
    }

    private function sendJson(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode)
    {
        parent::__construct($message);
    }
}