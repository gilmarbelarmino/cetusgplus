<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cetusg_plus');
define('DB_USER', 'root');
define('DB_PASS', '');

// Definir fuso horário global para o Brasil (Brasília)
date_default_timezone_set('America/Sao_Paulo');

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
    /**
     * Retorna os menus permitidos para um usuário.
     * Suporta chamadas: 
     *  - getUserMenus($userArray)
     *  - getUserMenus($pdo, $userId) 
     */
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
            return array_column($stmt->fetchAll(), 'menu') ?: [];
        } catch(Exception $e) { return []; }
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Não travar o PHP se o socket cair
        curl_exec($ch);
        curl_close($ch);
    }
}
