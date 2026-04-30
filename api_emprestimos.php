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

    $compId = getCurrentUserCompanyId();

    if ($method === 'GET') {
        $query = "SELECT l.*, un.name as unit_name, Rec.name as receiver_name, Bor.name as borrower_name, Bor.avatar_url as borrower_avatar, Ast.name as asset_name
                  FROM loans l 
                  LEFT JOIN units un ON BINARY l.unit_id = BINARY un.id
                  LEFT JOIN users Rec ON BINARY l.received_by_id = BINARY Rec.id
                  LEFT JOIN users Bor ON BINARY l.borrower_id = BINARY Bor.id
                  LEFT JOIN assets Ast ON BINARY l.asset_id = BINARY Ast.id
                  WHERE l.company_id = ?
                  ORDER BY l.loan_date DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$compId]);
        $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM assets WHERE status = 'Ativo' AND company_id = ?");
        $stmt->execute([$compId]);
        $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT u.id, u.name, u.sector, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.status = 'Ativo' AND u.company_id = ? ORDER BY u.name");
        $stmt->execute([$compId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => ['loans' => $loans, 'assets' => $assets, 'users' => $users]]);
        exit;
    }

    if ($method === 'POST') {
        if ($action === 'add_loan') {
            $data = $json_data ?? $_POST;
            $loan_id = 'L' . time() . rand(10, 99);
            
            // Buscar nome do asset para denormalização
            $ast = $pdo->prepare("SELECT name FROM assets WHERE id = ? AND company_id = ?");
            $ast->execute([$data['asset_id'], $compId]);
            $asset_name = $ast->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO loans (id, asset_id, asset_name, borrower_id, unit_id, sector, loan_date, observations, status, company_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?, NOW())");
            $stmt->execute([$loan_id, $data['asset_id'], $asset_name, $data['borrower_id'], $data['unit_id'], $data['sector'], $data['loan_date'], $data['observations'], $compId]);
            
            // Marcar asset como Emprestado
            $pdo->prepare("UPDATE assets SET status = 'Emprestado' WHERE id = ? AND company_id = ?")->execute([$data['asset_id'], $compId]);
            
            triggerSocketUpdate('data_updated', ['module' => 'emprestimos', 'action' => 'add']);
            echo json_encode(['success' => true, 'message' => 'Empréstimo registrado com sucesso', 'id' => $loan_id]);
            exit;
        }

        if ($action === 'return_loan') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("UPDATE loans SET status = 'Devolvido', return_date = NOW(), received_by_id = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['receiver_id'], $data['loan_id'], $compId]);

            // Pegar o asset_id para devolver ao estoque
            $loan = $pdo->prepare("SELECT asset_id FROM loans WHERE id = ? AND company_id = ?");
            $loan->execute([$data['loan_id'], $compId]);
            $aid = $loan->fetchColumn();
            if ($aid) {
                $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ? AND company_id = ?")->execute([$aid, $compId]);
            }

            triggerSocketUpdate('data_updated', ['module' => 'emprestimos', 'action' => 'return']);
            echo json_encode(['success' => true]);
            exit;
        }
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
