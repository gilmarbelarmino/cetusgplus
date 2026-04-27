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
    
    $current_id = $_POST['user_id'] ?? $_GET['user_id'] ?? $json_data['user_id'] ?? null;

    // --- LIMPEZA AUTOMÁTICA (30 DIAS) ---
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    $toDelete = $pdo->prepare("SELECT filename FROM semanada_uploads WHERE is_history = 1 AND uploaded_at < ?");
    $toDelete->execute([$thirtyDaysAgo]);
    $filesToDelete = $toDelete->fetchAll(PDO::FETCH_COLUMN);
    foreach ($filesToDelete as $f) {
        if (file_exists(__DIR__ . '/uploads/semanada/' . $f)) unlink(__DIR__ . '/uploads/semanada/' . $f);
    }
    $pdo->prepare("DELETE FROM semanada_uploads WHERE is_history = 1 AND uploaded_at < ?")->execute([$thirtyDaysAgo]);

    $uploadDir = __DIR__ . '/uploads/semanada/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if ($method === 'GET' && empty($action)) {
        // Verificar se expirou
        $pdo->exec("UPDATE semanada_uploads SET is_history = 1 WHERE is_history = 0 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");

        $uploadInfo = $pdo->query("
            SELECT su.*, u.name as uploader_name, u.avatar_url as uploader_avatar 
            FROM semanada_uploads su 
            LEFT JOIN users u ON BINARY su.uploaded_by = BINARY u.id 
            WHERE su.is_history = 0
            ORDER BY su.uploaded_at DESC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        $history = $pdo->query("
            SELECT su.*, u.name as uploader_name 
            FROM semanada_uploads su 
            LEFT JOIN users u ON BINARY su.uploaded_by = BINARY u.id 
            WHERE su.is_history = 1 
            ORDER BY su.uploaded_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $comments = $pdo->query("
            SELECT c.*, u.name as user_name, u.avatar_url as user_avatar
            FROM semanada_comments c
            LEFT JOIN users u ON BINARY c.user_id = BINARY u.id
            ORDER BY c.created_at ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => [
            'current' => $uploadInfo,
            'history' => $history,
            'comments' => $comments
        ]]);
        exit;
    }

    if ($method === 'POST') {
        $data = $json_data ?? $_POST;

        if ($action === 'upload_pdf' && isset($_FILES['pdf_file'])) {
            $file = $_FILES['pdf_file'];
            $expiryDate = $_POST['expiry_date'] ?? null;

            if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
                echo json_encode(['success' => false, 'error' => 'Extensão inválida']);
                exit;
            }

            // Historico
            $pdo->exec("UPDATE semanada_uploads SET is_history = 1 WHERE is_history = 0");

            $filename = 'semanada_' . date('Ymd_His') . '.pdf';
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $stmt = $pdo->prepare("INSERT INTO semanada_uploads (filename, original_name, uploaded_by, expiry_date, is_history) VALUES (?, ?, ?, ?, 0)");
                $stmt->execute([$filename, $file['name'], $current_id, $expiryDate]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Falha upload']);
            }
            exit;
        }

        if ($action === 'delete_pdf') {
            $old = glob($uploadDir . '*.pdf');
            foreach ($old as $f) unlink($f);
            $pdo->exec("DELETE FROM semanada_comments");
            $pdo->exec("DELETE FROM semanada_uploads");
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'add_comment') {
            $text = trim($data['comment_text'] ?? '');
            $parentId = !empty($data['parent_id']) ? intval($data['parent_id']) : null;
            if ($text !== '') {
                $stmt = $pdo->prepare("INSERT INTO semanada_comments (user_id, comment_text, parent_id) VALUES (?, ?, ?)");
                $stmt->execute([$current_id, $text, $parentId]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete_comment') {
            $cid = intval($data['comment_id']);
            $stmt = $pdo->prepare("DELETE FROM semanada_comments WHERE id = ? AND user_id = ?");
            $stmt->execute([$cid, $current_id]);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
