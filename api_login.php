<?php
// API para Autenticação do React (Fase 2)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Em produção, mude para o domínio exato
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'auth.php'; // Usa as funções existentes

// Ler dados JSON enviados pelo Vue/React (axios ou fetch)
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['login_name']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'error' => 'Por favor, envie o usuário e a senha.']);
    exit;
}

$loginName = trim($data['login_name']);
$password = trim($data['password']);

try {
    if (login($loginName, $password)) { // Usa a função existente com todas as validações (atestado, etc)
        // Login com Sucesso
        $user = getCurrentUser(); // Pega o usuário logado na sessão ou token virtual
        $menus = getUserMenus($user);
        
        // Retorna Token Simplificado (Como base 64 do userId para MVP - depois substituiremos por JWT real)
        $token = base64_encode(json_encode(['user_id' => $user['id'], 'time' => time()]));
        
        echo json_encode([
            'success' => true,
            'message' => 'Login realizado com sucesso',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
                'avatar' => $user['avatar'] ?? null,
                'menus' => $menus
            ]
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuário ou senha incorretos.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
