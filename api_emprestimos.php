<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $_GET['action'] ?? $json_data['action'] ?? '';
    
    if (!$action && isset($json_data['action'])) {
        $_POST = $json_data;
    }

    if ($method === 'GET') {
        $query = "SELECT l.*, un.name as unit_name, Rec.name as receiver_name, Bor.name as borrower_name, Bor.avatar_url as borrower_avatar, Ast.name as asset_name
                  FROM loans l 
                  LEFT JOIN units un ON BINARY l.unit_id = BINARY un.id
                  LEFT JOIN users Rec ON BINARY l.received_by_id = BINARY Rec.id
                  LEFT JOIN users Bor ON BINARY l.borrower_id = BINARY Bor.id
                  LEFT JOIN assets Ast ON BINARY l.asset_id = BINARY Ast.id
                  ORDER BY l.loan_date DESC";
        $loans = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        $assets = $pdo->query("SELECT * FROM assets WHERE status = 'Ativo'")->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query("SELECT u.id, u.name, u.sector, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.status = 'Ativo' ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => ['loans' => $loans, 'assets' => $assets, 'users' => $users]]);
        exit;
    }

    if ($method === 'POST') {
        if ($action === 'add_loan') {
            $data = $json_data ?? $_POST;
            $user_id = 'L' . time() . rand(10, 99);
            
            // Buscar nome do asset e do borrower para denormalização se as colunas existirem
            $ast = $pdo->prepare("SELECT name FROM assets WHERE id = ?");
            $ast->execute([$data['asset_id']]);
            $asset_name = $ast->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO loans (id, asset_id, asset_name, borrower_id, unit_id, sector, loan_date, observations, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', NOW())");
            $stmt->execute([$user_id, $data['asset_id'], $asset_name, $data['borrower_id'], $data['unit_id'], $data['sector'], $data['loan_date'], $data['observations']]);
            
            // Marcar asset como Emprestado
            $pdo->prepare("UPDATE assets SET status = 'Emprestado' WHERE id = ?")->execute([$data['asset_id']]);
            
            triggerSocketUpdate('data_updated', ['module' => 'emprestimos', 'action' => 'add']);
            echo json_encode(['success' => true, 'message' => 'Empréstimo registrado com sucesso', 'id' => $user_id]);
            exit;
        }

        if ($action === 'return_loan') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("UPDATE loans SET status = 'Devolvido', return_date = NOW(), received_by_id = ? WHERE id = ?");
            $stmt->execute([$data['receiver_id'], $data['loan_id']]);

            // Pegar o asset_id para devolver ao estoque
            $loan = $pdo->prepare("SELECT asset_id FROM loans WHERE id = ?");
            $loan->execute([$data['loan_id']]);
            $aid = $loan->fetchColumn();
            if ($aid) $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ?")->execute([$aid]);

            triggerSocketUpdate('data_updated', ['module' => 'emprestimos', 'action' => 'return']);
            echo json_encode(['success' => true]);
            exit;
        }
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
