<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $_GET['action'] ?? $json_data['action'] ?? '';
    
    // Autenticacao via Payload/Query para o React
    $current_id = $_POST['user_id'] ?? $_GET['user_id'] ?? $json_data['user_id'] ?? null;
    
    if (!$current_id) {
        echo json_encode(['error' => 'Usuário não autenticado no Chat']);
        exit;
    }

    if (!$action && isset($json_data['action'])) {
        $_POST = $json_data;
    }

    // Atualizar last_activity
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$current_id]);
    } catch(Exception $e) {}

    // Limpeza automática de mensagens > 30 dias (probabilidade de 5%)
    if (rand(1, 100) <= 5) {
        $pdo->query("DELETE FROM chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }

    switch ($action) {
        case 'list_users':
            $compId = getCurrentUserCompanyId();
            $loginName = $pdo->query("SELECT login_name FROM users WHERE id = '$current_id'")->fetchColumn();
            $isSuper = ($loginName === 'superadmin');

            if ($isSuper) {
                // Super Admin vê todos os usuários (especialmente os admins das empresas)
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.avatar_url, u.last_activity, u.company_id,
                    (SELECT name FROM tenants WHERE id = u.company_id) as company_name,
                    (SELECT COUNT(*) FROM chat_messages m 
                     LEFT JOIN chat_clears c ON c.user_id = ? AND c.other_id = u.id
                     WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0 
                     AND (c.cleared_at IS NULL OR m.created_at > c.cleared_at)
                    ) as unread_count,
                    (CASE WHEN u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as is_online
                    FROM users u
                    WHERE u.status = 'Ativo' AND u.id != ?
                    ORDER BY u.company_id ASC, is_online DESC, u.name ASC
                ");
                $stmt->execute([$current_id, $current_id, $current_id]);
            } else {
                // Usuário comum vê apenas pessoas da mesma empresa + o Super Admin (Suporte)
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.avatar_url, u.last_activity,
                    (SELECT COUNT(*) FROM chat_messages m 
                     LEFT JOIN chat_clears c ON c.user_id = ? AND c.other_id = u.id
                     WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0 
                     AND (c.cleared_at IS NULL OR m.created_at > c.cleared_at)
                    ) as unread_count,
                    (CASE WHEN u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as is_online
                    FROM users u
                    WHERE u.status = 'Ativo' AND u.id != ? 
                    AND (u.company_id = ? OR u.login_name = 'superadmin')
                    ORDER BY is_online DESC, u.name ASC
                ");
                $stmt->execute([$current_id, $current_id, $current_id, $compId]);
            }
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($users);
            break;

        case 'send':
            $data = $json_data ?? $_POST;
            $receiver_id = $data['receiver_id'] ?? '';
            $content = trim($data['content'] ?? '');
            $type = $data['type'] ?? 'text';
            $compId = getCurrentUserCompanyId();
            
            if ($receiver_id && $content) {
                // Se for superadmin enviando, a mensagem deve pertencer à empresa do destinatário para isolamento
                $loginName = $pdo->query("SELECT login_name FROM users WHERE id = '$current_id'")->fetchColumn();
                if ($loginName === 'superadmin') {
                    $compId = $pdo->query("SELECT company_id FROM users WHERE id = '$receiver_id'")->fetchColumn() ?: $compId;
                }

                // Salvar mensagem
                $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type, company_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$current_id, $receiver_id, $content, $type, $compId]);

                // SE O DESTINATÁRIO FOR O PEIXINHO IA
                if ($receiver_id === 'U_PEIXINHO') {
                    require_once 'peixinho_api.php';
                    $user_menus = $pdo->query("SELECT menu FROM user_menus WHERE user_id = '$current_id'")->fetchAll(PDO::FETCH_COLUMN);
                    $brain = new PeixinhoBrain($pdo, $user_menus);
                    $response = $brain->process($content);
                    
                    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type, is_read, read_at, company_id) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, ?)");
                    $stmt->execute(['U_PEIXINHO', $current_id, $response, 'text', $compId]);
                    
                    echo json_encode(['success' => true, 'is_peixinho' => true, 'peixinho_response' => $response]);
                    exit;
                }

                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Dados incompletos']);
            }
            break;

        case 'upload_file':
            if (isset($_FILES['file']) && isset($_POST['receiver_id'])) {
                $receiver_id = $_POST['receiver_id'];
                $file = $_FILES['file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'chat_' . time() . '_' . rand(100, 999) . '.' . $ext;
                
                $uploadDir = __DIR__ . '/uploads/chat_files/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $type = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'file';
                    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$current_id, $receiver_id, $filename, $type]);
                    echo json_encode(['success' => true, 'filename' => $filename, 'type' => $type]);
                } else {
                    echo json_encode(['error' => 'Falha ao salvar arquivo']);
                }
            }
            break;

        case 'clear_chat':
            $data = $json_data ?? $_POST;
            $other_id = $data['other_id'] ?? '';
            if ($other_id) {
                $stmt = $pdo->prepare("INSERT INTO chat_clears (user_id, other_id, cleared_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE cleared_at = CURRENT_TIMESTAMP");
                $stmt->execute([$current_id, $other_id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'get_messages':
            $other_id = $_GET['other_id'] ?? $json_data['other_id'] ?? '';
            if ($other_id) {
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                    DATE_FORMAT(m.created_at, '%d/%m às %H:%i') as time_formatted,
                    DATE_FORMAT(m.read_at, '%d/%m às %H:%i') as read_formatted
                    FROM chat_messages m
                    LEFT JOIN chat_clears c ON c.user_id = ? AND c.other_id = ?
                    WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                    AND (c.cleared_at IS NULL OR m.created_at > c.cleared_at)
                    ORDER BY m.created_at ASC
                    LIMIT 100
                ");
                $stmt->execute([$current_id, $other_id, $current_id, $other_id, $other_id, $current_id]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($messages);
            }
            break;

        case 'mark_as_read':
            $other_id = $_GET['other_id'] ?? '';
            if ($other_id) {
                $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
                $stmt->execute([$other_id, $current_id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'total_unread':
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM chat_messages m
                LEFT JOIN chat_clears c ON c.user_id = ? AND c.other_id = m.sender_id
                WHERE m.receiver_id = ? AND m.is_read = 0
                AND (c.cleared_at IS NULL OR m.created_at > c.cleared_at)
            ");
            $stmt->execute([$current_id, $current_id]);
            $count = $stmt->fetchColumn();
            echo json_encode(['count' => (int)$count]);
            break;

        default:
            echo json_encode(['error' => 'Ação inválida']);
            break;
    }

} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
