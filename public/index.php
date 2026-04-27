<?php

/**
 * CETUSG - Front Controller MVC
 * ==============================
 * Ponto de entrada único para rotas limpas.
 * O sistema legado (index.php?page=...) continua funcionando em paralelo.
 */

// Autoload de classes (PSR-4 manual)
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
require_once __DIR__ . '/../config.php';

// Injetar PDO nas classes Core
\App\Core\Model::setConnection($pdo);
\App\Core\Logger::setConnection($pdo);

// Verificação CSRF em todas as requisições POST
\App\Core\Csrf::check();

// ============================
// DEFINIÇÃO DE ROTAS
// ============================
$router = new \App\Core\Router();

// Tecnologia
$router->add('GET', '/tecnologia', 'TecnologiaController@index');
$router->add('POST', '/tecnologia', 'TecnologiaController@store');

// Futuras rotas (adicionar conforme migração):
// $router->add('GET', '/dashboard', 'DashboardController@index');
// $router->add('GET', '/emprestimos', 'EmprestimosController@index');
// $router->add('POST', '/emprestimos', 'EmprestimosController@store');
// $router->add('GET', '/usuarios', 'UsuariosController@index');
// $router->add('GET', '/relatorios', 'RelatoriosController@index');
// $router->add('GET', '/configuracoes', 'ConfiguracoesController@index');

// ============================
// API REST (Futuro)
// ============================
// $router->add('GET', '/api/users', 'Api\UserController@index');
// $router->add('GET', '/api/dashboard', 'Api\DashboardController@stats');

// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
