<?php
require_once __DIR__ . '/ErrorLogger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Detecção Inteligente de Ambiente (Local vs Produção)
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (strpos($httpHost, 'localhost') !== false || strpos($httpHost, '127.0.0.1') !== false || strpos($httpHost, '192.168.') !== false || PHP_SAPI === 'cli');

// Gatilho remoto para Otimização de Banco de Dados (evita 404 se o deploy do script falhar)
if (isset($_GET['run_index']) && $_GET['run_index'] === 'secret') {
    $tables = ['users', 'volunteers', 'volunteer_history', 'time_records', 'time_incidents', 'company_settings', 'units', 'sectors'];
    echo "<h3>Otimizando Banco de Dados...</h3><ul>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'idx_company_id'");
            if ($stmt->rowCount() == 0) {
                $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_company_id` (`company_id`)");
                echo "<li>OK: Índice adicionado em $table</li>";
            } else {
                echo "<li>INFO: Índice já existia em $table</li>";
            }
        } catch (Exception $e) { echo "<li>ERRO em $table: " . $e->getMessage() . "</li>"; }
    }
    echo "</ul><p>Otimização concluída! Você já pode voltar a usar o sistema normalmente.</p>";
    exit;
}

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'cetusg_plus');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // CONFIGURAÇÕES DA HOSTINGER (Ativadas automaticamente na nuvem)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u339905928_u123_cetusg');     // Nome COMPLETO do banco
    define('DB_USER', 'u339905928_cetusgbelo');      // Usuário COMPLETO
    define('DB_PASS', 'Profgilbelo@83');             // Senha permanece a mesma
}

// Forçar o caminho do cookie de sessão para ser compartilhado entre a raiz e a pasta public
$sessionPath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (strpos($sessionPath, '/public') !== false) {
    $sessionPath = str_replace('/public', '', $sessionPath);
}
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => $sessionPath ?: '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Definir fuso horário global para o Brasil (Brasília)
date_default_timezone_set('America/Sao_Paulo');

// Detecção automática da URL Base para suporte a rede local e VHosts
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Remove /public se estiver no final (caso o Apache aponte para a raiz do projeto)
$scriptDir = preg_replace('/\/public$/', '', $scriptDir);
if ($scriptDir === '/') $scriptDir = '';
define('URL_BASE', $scriptDir);
define('FULL_URL', $protocol . "://" . $host . URL_BASE);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8mb4");
    // Sincronizar fuso horário do MySQL com o PHP
    $pdo->exec("SET time_zone = '-03:00'");
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Funções Auxiliares Globais
if (!function_exists('getUserMenus')) {
    function getUserMenus($user, $user_id = null) {
        global $pdo;
        $target_pdo = $pdo;
        $target_user_id = null;
        if ($user instanceof PDO && $user_id !== null) {
            $target_pdo = $user;
            $target_user_id = $user_id;
        } elseif (is_array($user) && isset($user['id'])) {
            $target_user_id = $user['id'];
        } elseif (is_string($user)) {
            $target_user_id = $user;
        }
        if (!$target_user_id) return [];
        try {
            $stmt = $target_pdo->prepare("SELECT menu FROM user_menus WHERE user_id = ?");
            $stmt->execute([$target_user_id]);
            $menus = array_column($stmt->fetchAll(), 'menu') ?: [];
            
            // Logica SaaS: Se for o usuario superadmin, ele tem ACESSO TOTAL a tudo
            $checkAdmin = $target_pdo->prepare("SELECT login_name FROM users WHERE id = ?");
            $checkAdmin->execute([$target_user_id]);
            $loginName = $checkAdmin->fetchColumn();

            if ($loginName === 'superadmin') {
                return [
                    'super_admin', 'rh', 'ponto', 'voluntariado', 'semanada', 'patrimonio', 
                    'emprestimos', 'chamados', 'orcamentos', 'locacao_salas', 
                    'relatorios', 'tecnologia', 'informacoes', 'usuarios', 'configuracoes'
                ];
            }
            $menus[] = 'pesquisa'; // Todos tem acesso ao menu de pesquisa
            return $menus;
        } catch(Exception $e) { return []; }
    }
}

if (!function_exists('getCurrentUserCompanyId')) {
    function getCurrentUserCompanyId() {
        if (isset($_SESSION['company_id'])) return (int)$_SESSION['company_id'];
        if (!isset($_SESSION['user_id'])) return 0;
        
        global $pdo;
        $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $compId = $stmt->fetchColumn();
        $_SESSION['company_id'] = ($compId !== false && $compId !== null) ? (int)$compId : 0;
        return $_SESSION['company_id'];
    }
}

if (!function_exists('triggerSocketUpdate')) {
    function triggerSocketUpdate($event, $data = []) {
        $url = 'http://localhost:3001/notify';
        $payload = json_encode(['event' => $event, 'data' => $data]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
        curl_exec($ch);
        curl_close($ch);
    }
}
