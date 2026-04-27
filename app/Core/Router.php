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
        // Remover a base da URL se o sistema estiver em uma subpasta
        $basePath = '/cetusg';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['uri'] === $uri && $route['method'] === $method) {
                return $this->callAction($route['controller']);
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo "404 - Página não encontrada";
    }

    protected function callAction($controllerAction) {
        list($controller, $action) = explode('@', $controllerAction);
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
