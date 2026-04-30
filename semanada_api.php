<?php
// ============================================================
// API de Comentários da Semanada (AJAX)
// ============================================================
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}
$user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// LISTAR COMENTÁRIOS
if ($action === 'list') {
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as user_name, u.avatar_url as user_avatar
        FROM semanada_comments c
        LEFT JOIN users u ON BINARY c.user_id = BINARY u.id
        WHERE c.company_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$compId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

// ADICIONAR COMENTÁRIO
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['comment_text'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    if ($text === '') {
        echo json_encode(['error' => 'Texto vazio']);
        exit;
    }
    
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("INSERT INTO semanada_comments (user_id, comment_text, parent_id, company_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $text, $parentId, $compId]);
    
    $newId = $pdo->lastInsertId();
    $comment = $pdo->prepare("
        SELECT c.*, u.name as user_name, u.avatar_url as user_avatar
        FROM semanada_comments c
        LEFT JOIN users u ON BINARY c.user_id = BINARY u.id
        WHERE c.id = ? AND c.company_id = ?
    ");
    $comment->execute([$newId, $compId]);
    
    echo json_encode(['success' => true, 'comment' => $comment->fetch(PDO::FETCH_ASSOC)]);
    exit;
}

// DELETAR COMENTÁRIO
    $compId = getCurrentUserCompanyId();
    $cid = intval($_POST['comment_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM semanada_comments WHERE id = ? AND user_id = ? AND company_id = ?");
    $stmt->execute([$cid, $user['id'], $compId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Ação inválida']);
