<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    public function get(string $path, string $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, string $handler): void { $this->routes['POST'][$path] = $handler; }
    public function put(string $path, string $handler): void { $this->routes['PUT'][$path] = $handler; }
    public function patch(string $path, string $handler): void { $this->routes['PATCH'][$path] = $handler; }
    public function delete(string $path, string $handler): void { $this->routes['DELETE'][$path] = $handler; }

    public function dispatch(string $method, string $path): void
    {
        // Remove query string from path
        $cleanPath = strtok($path, '?');
        error_log('[Router] Dispatching: ' . $method . ' ' . $path . ' (clean: ' . $cleanPath . ')');
        error_log('[Router] Registered routes for ' . $method . ': ' . json_encode(array_keys($this->routes[$method])));
        
        // Debug: Check if the exact path exists
        if (isset($this->routes[$method][$cleanPath])) {
            error_log('[Router] Found exact match: ' . $cleanPath);
        } else {
            error_log('[Router] No exact match for: ' . $cleanPath);
            error_log('[Router] Available GET routes: ' . json_encode(array_keys($this->routes['GET'])));
        }
        
        $handler = $this->routes[$method][$cleanPath] ?? null;
        if (!$handler) {
            error_log('[Router] No handler found for: ' . $method . ' ' . $cleanPath);
            error_log('[Router] Available GET routes: ' . json_encode(array_keys($this->routes['GET'])));
            http_response_code(404);
            echo 'Not Found';
            return;
        }
        [$class, $action] = explode('@', $handler);
        $fqcn = 'App\\Controllers\\' . $class;
        if (!class_exists($fqcn) || !method_exists($fqcn, $action)) {
            http_response_code(500);
            echo 'Handler Not Available';
            return;
        }
        $controller = new $fqcn();
        $controller->$action();
    }
}


