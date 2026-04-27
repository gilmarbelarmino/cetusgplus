<?php

namespace App\Core;

class Router {
    protected $routes = [];

    public function add($method, $uri, $controller) {
        $this->routes[] = [
            'method' => $method,
            'uri' => $uri,
            'controller' => $controller
        ];
    }

    public function dispatch($uri, $method) {
        // Remover a base da URL
        $basePath = '/cetusg';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        // Remover query string para matching
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        
        // Remover trailing slash (exceto root)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['uri'] === $uri && $route['method'] === $method) {
                return $this->callAction($route['controller']);
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo '<div style="text-align:center;padding:4rem;font-family:sans-serif;">
            <h1 style="color:#1e293b;">404</h1>
            <p style="color:#64748b;">Página não encontrada</p>
            <a href="/cetusg/" style="color:#6366f1;text-decoration:none;font-weight:700;">← Voltar ao Dashboard</a>
        </div>';
    }

    protected function callAction($controllerAction) {
        list($controller, $action) = explode('@', $controllerAction);
        
        // Suporte a namespaces aninhados (ex: Api\DashboardApiController)
        $controllerClass = "App\\Controllers\\$controller";
        
        if (class_exists($controllerClass)) {
            $instance = new $controllerClass();
            if (method_exists($instance, $action)) {
                return $instance->$action();
            }
        }
        
        throw new \Exception("Controller ou Ação não encontrada: $controllerAction");
    }
}
