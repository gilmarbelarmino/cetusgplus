<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$user = getCurrentUser();
$current_id = $user['id'];

// Atualizar last_activity
try {
    $stmt = $pdo->prepare("UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$current_id]);
} catch(Exception $e) {}

// Limpeza automática de mensagens > 30 dias
if (rand(1, 100) <= 5) {
    $pdo->query("DELETE FROM chat_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)");
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list_users':
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
            AND (u.id != 'U_PEIXINHO' OR (SELECT COUNT(*) FROM user_menus WHERE user_id = ? AND menu = 'peixinho') > 0)
            ORDER BY is_online DESC, u.name ASC
        ");
        $stmt->execute([$current_id, $current_id, $current_id, $current_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
        break;

    case 'send':
        $receiver_id = $_POST['receiver_id'] ?? '';
        $content = trim($_POST['content'] ?? '');
        $type = $_POST['type'] ?? 'text';
        if ($receiver_id && $content) {
            // Salvar mensagem do usuário
            $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$current_id, $receiver_id, $content, $type]);

            // SE O DESTINATÁRIO FOR O PEIXINHO IA
            if ($receiver_id === 'U_PEIXINHO') {
                require_once 'peixinho_api.php';
                $user_menus = getUserMenus($user);
                $brain = new PeixinhoBrain($pdo, $user_menus);
                $response = $brain->process($content);
                
                // Salvar resposta do Peixinho
                $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type, is_read, read_at) VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP)");
                $stmt->execute(['U_PEIXINHO', $current_id, $response, 'text']);
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
        $other_id = $_POST['other_id'] ?? '';
        if ($other_id) {
            $stmt = $pdo->prepare("INSERT INTO chat_clears (user_id, other_id, cleared_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE cleared_at = CURRENT_TIMESTAMP");
            $stmt->execute([$current_id, $other_id]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'get_messages':
        $other_id = $_GET['other_id'] ?? '';
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
?>
