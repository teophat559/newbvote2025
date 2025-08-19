<?php
namespace App\Core;

class Router
{
    private array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []];

    public function get(string $pattern, callable|array $handler): void { $this->routes['GET'][$pattern] = $handler; }
    public function post(string $pattern, callable|array $handler): void { $this->routes['POST'][$pattern] = $handler; }
    public function put(string $pattern, callable|array $handler): void { $this->routes['PUT'][$pattern] = $handler; }
    public function delete(string $pattern, callable|array $handler): void { $this->routes['DELETE'][$pattern] = $handler; }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($method === 'POST' && isset($_POST['_method'])) { $method = strtoupper($_POST['_method']); }
        Security::enforceCsrf($method);
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex = '#^' . $pattern . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                if (is_array($handler)) { [$class, $action] = $handler; (new $class())->$action(...$matches); return; }
                call_user_func_array($handler, $matches); return;
            }
        }
        http_response_code(404);
        // Render the unified 404 page within the public layout
        View::render('pages/404.php');
    }
}
