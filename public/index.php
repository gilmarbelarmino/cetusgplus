<?php

// Front Controller - Cetusg MVC

// Autoload de classes simples (PSR-4 manual)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Inicializar Sessão e Configurações
session_start();
require_once __DIR__ . '/../config.php'; // Reutilizando a conexão PDO existente

// Injetar PDO na base do Model
\App\Core\Model::setConnection($pdo);

// Definir Rotas
$router = new \App\Core\Router();

// Rota de exemplo para Tecnologia
$router->add('GET', '/tecnologia', 'TecnologiaController@index');
$router->add('POST', '/tecnologia', 'TecnologiaController@store');

// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
