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
\App\Core\Auth::setConnection($pdo);

// Verificação CSRF em todas as requisições POST
\App\Core\Csrf::check();

// ============================
// DEFINIÇÃO DE ROTAS
// ============================
$router = new \App\Core\Router();

// --- Módulos (Views com layout) ---
$router->add('GET', '/', 'DashboardController@index');
$router->add('GET', '/dashboard', 'DashboardController@index');
$router->add('GET', '/tecnologia', 'TecnologiaController@index');
$router->add('POST', '/tecnologia', 'TecnologiaController@store');

// --- API REST (JSON) ---
$router->add('GET', '/api/dashboard', 'Api\\DashboardApiController@index');
$router->add('GET', '/api/dashboard/stats', 'Api\\DashboardApiController@stats');
$router->add('GET', '/api/audit', 'Api\\AuditApiController@index');

// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
