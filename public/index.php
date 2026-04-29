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

// Inicializar Configurações (que agora cuidam da Sessão de forma inteligente)
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
$router->add('GET', '/login', 'AuthController@login');
$router->add('POST', '/login', 'AuthController@authenticate');
$router->add('GET', '/logout', 'AuthController@logout');

$router->add('GET', '/', 'DashboardController@index');
$router->add('GET', '/dashboard', 'DashboardController@index');
$router->add('GET', '/informacoes', 'InfoController@index');
$router->add('POST', '/informacoes', 'InfoController@store');

$router->add('GET', '/tecnologia', 'TechnologyController@index');
$router->add('POST', '/tecnologia', 'TechnologyController@store');

$router->add('GET', '/usuarios', 'UserController@index');
$router->add('POST', '/usuarios', 'UserController@store');

$router->add('GET', '/rh', 'RHController@index');
$router->add('POST', '/rh', 'RHController@store');
$router->add('GET', '/rh/voluntariado', 'RHController@voluntariado');
$router->add('POST', '/rh/voluntariado', 'RHController@storeVoluntariado');

$router->add('GET', '/chamados', 'TicketController@index');
$router->add('POST', '/chamados', 'TicketController@store');

$router->add('GET', '/patrimonio', 'AssetController@index');
$router->add('POST', '/patrimonio', 'AssetController@store');

$router->add('GET', '/emprestimos', 'LoanController@index');
$router->add('POST', '/emprestimos', 'LoanController@store');
$router->add('GET', '/patrimonio/historico', 'AssetController@history');

$router->add('GET', '/configuracoes/roles', 'RoleController@index');
$router->add('POST', '/configuracoes/roles', 'RoleController@store');

$router->add('GET', '/orcamentos', 'BudgetController@index');
$router->add('POST', '/orcamentos', 'BudgetController@store');

$router->add('GET', '/locacao_salas', 'BookingController@index');
$router->add('POST', '/locacao_salas', 'BookingController@store');

$router->add('GET', '/relatorios', 'ReportController@index');

$router->add('GET', '/semanada', 'SemanadaController@index');
$router->add('POST', '/semanada', 'SemanadaController@store');

$router->add('GET', '/configuracoes', 'ConfigController@index');
$router->add('POST', '/configuracoes', 'ConfigController@store');

// --- API REST (JSON) ---
$router->add('GET', '/api/dashboard', 'Api\\DashboardApiController@index');
$router->add('GET', '/api/dashboard/stats', 'Api\\DashboardApiController@stats');
$router->add('GET', '/api/audit', 'Api\\AuditApiController@index');
$router->add('GET', '/api/assets', 'Api\\AssetApiController@index');
$router->add('GET', '/api/assets/{id}', 'Api\\AssetApiController@show');

// Dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
