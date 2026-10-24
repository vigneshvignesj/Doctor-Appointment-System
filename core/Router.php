<?php

namespace Core;

class Router
{
    private array $routes = ['GET' => [], 'POST' => []];
    private static string $basePath = '';

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . self::$basePath . $path);
        exit;
    }

    public static function url(string $path = ''): string
    {
        return self::$basePath . $path;
    }

    public function get(string $pattern, array $handler): void
    {
        $this->routes['GET'][$pattern] = $handler;
    }

    public function post(string $pattern, array $handler): void
    {
        $this->routes['POST'][$pattern] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        if (self::$basePath !== '' && str_starts_with($path, self::$basePath)) {
            $path = substr($path, strlen(self::$basePath));
        }
        if ($path === '') {
            $path = '/';
        }
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $params = $this->match($pattern, $path);
            if ($params !== null) {
                [$controllerClass, $action] = $handler;
                $controller = new $controllerClass();
                call_user_func_array([$controller, $action], $params);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../app/views/errors/404.php';
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern);
        $regex = rtrim($regex, '/');
        if ($regex === '') {
            $regex = '/';
        }
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        array_shift($matches);
        return $matches;
    }
}
