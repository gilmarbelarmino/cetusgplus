<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Ler JSON payload
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $data['action'] ?? $_GET['action'] ?? '';

    // GET: Listagem e dependências (Autocomplete)
    if ($method === 'GET' && empty($action)) {
        $show_all = isset($_GET['all']) && $_GET['all'] == '1';
        $conditions = ["1=1"];
        if (!$show_all) {
            $conditions[] = "(t.status = 'Aberto' OR t.status = 'Pendente')";
        }

        $query = "SELECT t.*, u.name as requester_name, u.avatar_url as requester_avatar, un.name as unit_name, a.name as asset_name, c_user.avatar_url as closer_avatar 
                  FROM tickets t 
                  LEFT JOIN users u ON BINARY t.requester_id = BINARY u.id 
                  LEFT JOIN units un ON BINARY t.unit_id = BINARY un.id 
                  LEFT JOIN assets a ON t.asset_id = a.id
                  LEFT JOIN users c_user ON BINARY t.closed_by = BINARY c_user.name
                  WHERE " . implode(" AND ", $conditions) . " 
                  ORDER BY t.created_at DESC";

        $tickets = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query("SELECT u.id, u.name, u.sector, u.role, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
        $assets = $pdo->query("SELECT id, name, patrimony_id FROM assets ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $units = $pdo->query("SELECT * FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true, 
            'data' => [
                'tickets' => $tickets,
                'users' => $users,
                'assets' => $assets,
                'units' => $units
            ]
        ]);
        exit;
    }

    // POST: Criar Chamado
    if ($method === 'POST' && $action === 'add_ticket') {
        $stmt = $pdo->prepare("INSERT INTO tickets (id, asset_id, title, description, priority, requester_id, sector, unit_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Aberto', NOW())");
        $stmt->execute(['T' . time(), $data['asset_id'] ?: null, $data['title'], $data['description'], $data['priority'], $data['requester_id'], $data['sector'], $data['unit_id']]);
        
        triggerSocketUpdate('data_updated', ['module' => 'chamados', 'action' => 'add']);
        echo json_encode(['success' => true, 'message' => 'Chamado criado com sucesso']);
        exit;
    }

    // POST/PUT: Editar Chamado
    if ($method === 'POST' && $action === 'edit_ticket') {
        $stmt = $pdo->prepare("UPDATE tickets SET title = ?, description = ?, priority = ?, asset_id = ? WHERE id = ?");
        $stmt->execute([$data['title'], $data['description'], $data['priority'], $data['asset_id'] ?: null, $data['ticket_id']]);
        
        triggerSocketUpdate('data_updated', ['module' => 'chamados', 'action' => 'edit']);
        echo json_encode(['success' => true, 'message' => 'Chamado atualizado']);
        exit;
    }

    // POST/PUT: Fechar/Resolver Chamado
    if ($method === 'POST' && $action === 'close_ticket') {
        $ticket_id = $data['ticket_id'];
        $resolution = $data['resolution']; // 'solucionado', 'pendente', 'sem_solucao'
        $user_name = $data['closed_by']; // Nome do usuario fechando (enviado pelo React)

        $t = $pdo->prepare("SELECT asset_id FROM tickets WHERE id = ?");
        $t->execute([$ticket_id]);
        $ticket_data = $t->fetch(PDO::FETCH_ASSOC);

        $new_status = 'Concluído';
        $release_asset = false;

        if ($resolution === 'solucionado') {
            $new_status = 'Concluído';
            $release_asset = true;
        } elseif ($resolution === 'pendente') {
            $new_status = 'Pendente';
            $release_asset = false;
        } elseif ($resolution === 'sem_solucao') {
            $new_status = 'Sem Solução';
            $release_asset = true;
        }

        $stmt = $pdo->prepare("UPDATE tickets SET status = ?, closed_by = ?, closed_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $user_name, $ticket_id]);

        if ($release_asset && $ticket_data && $ticket_data['asset_id']) {
            $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ?")->execute([$ticket_data['asset_id']]);
        }

        triggerSocketUpdate('data_updated', ['module' => 'chamados', 'action' => 'close']);
        echo json_encode(['success' => true, 'message' => 'Chamado finalizado com status: ' . $new_status]);
        exit;
    }

    // Caminho não encontrado
    echo json_encode(['success' => false, 'error' => 'Ação não reconhecida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
