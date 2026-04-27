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
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Lida com payload em JSON se não for Form-Data
    $json_data = json_decode(file_get_contents('php://input'), true);
    if (!$action && isset($json_data['action'])) {
        $action = $json_data['action'];
        $_POST = $json_data;
    }

    if ($method === 'GET' && empty($action)) {
        // Listagem
        $query = "SELECT a.*, u.name as unit_name FROM assets a LEFT JOIN units u ON BINARY a.unit_id = BINARY u.id ORDER BY a.created_at DESC";
        $assets = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        $units = $pdo->query("SELECT * FROM units")->fetchAll(PDO::FETCH_ASSOC);
        $categories = $pdo->query("SELECT DISTINCT category FROM assets WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_ASSOC);
        $sectors = $pdo->query("SELECT DISTINCT sector FROM assets WHERE sector IS NOT NULL AND sector != '' ORDER BY sector")->fetchAll(PDO::FETCH_ASSOC);
        $all_users = $pdo->query("SELECT u.id, u.name, u.email, u.phone, u.sector, u.role, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'assets' => $assets,
                'units' => $units,
                'categories' => $categories,
                'sectors' => $sectors,
                'users' => $all_users
            ]
        ]);
        exit;
    }

    if ($method === 'GET' && $action === 'history') {
        $hist_id = $_GET['id'];
        
        $asset_info = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $asset_info->execute([$hist_id]);
        $asset_data = $asset_info->fetch(PDO::FETCH_ASSOC);
        
        $loan_hist = $pdo->prepare("SELECT * FROM loans WHERE asset_id = ? ORDER BY loan_date DESC");
        $loan_hist->execute([$hist_id]);
        $loans = $loan_hist->fetchAll(PDO::FETCH_ASSOC);
        
        $ticket_hist = $pdo->prepare("SELECT t.*, u.name as req_name FROM tickets t LEFT JOIN users u ON BINARY t.requester_id = BINARY u.id WHERE t.asset_id = ? ORDER BY t.created_at DESC");
        $ticket_hist->execute([$hist_id]);
        $tickets = $ticket_hist->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => [
            'asset' => $asset_data, 'loans' => $loans, 'tickets' => $tickets
        ]]);
        exit;
    }

    if ($method === 'POST' && $action === 'add_asset') {
        $image_name = null;
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
        }
        
        $stmt = $pdo->prepare("INSERT INTO assets (id, name, category, patrimony_id, sector, unit_id, status, responsible_name, estimated_value, image_url) VALUES (?, ?, ?, ?, ?, ?, 'Ativo', ?, ?, ?)");
        $estimated = floatval(str_replace(['.', ','], ['', '.'], $_POST['estimated_value'] ?? '0'));
        $patrimony_id = !empty($_POST['patrimony_id']) ? $_POST['patrimony_id'] : null;
        
        $stmt->execute(['A' . time(), $_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['responsible_name'], $estimated, $image_name]);
        
        triggerSocketUpdate('data_updated', ['module' => 'patrimonio', 'action' => 'add']);
        echo json_encode(['success' => true, 'message' => 'Ativo criado com sucesso']);
        exit;
    }

    if ($method === 'POST' && $action === 'edit_asset') {
        $estimated = floatval(str_replace(['.', ','], ['', '.'], $_POST['estimated_value'] ?? '0'));
        $image_update = "";
        $patrimony_id = !empty($_POST['patrimony_id']) ? $_POST['patrimony_id'] : null;
        
        $params = [$_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['status'], $_POST['responsible_name'], $estimated];
        
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
            $image_update = ", image_url = ?";
            $params[] = $image_name;
        }
        
        $params[] = $_POST['asset_id'];
        
        $stmt = $pdo->prepare("UPDATE assets SET name = ?, category = ?, patrimony_id = ?, sector = ?, unit_id = ?, status = ?, responsible_name = ?, estimated_value = ? $image_update WHERE id = ?");
        $stmt->execute($params);
        
        triggerSocketUpdate('data_updated', ['module' => 'patrimonio', 'action' => 'edit']);
        echo json_encode(['success' => true, 'message' => 'Ativo atualizado com sucesso']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação não reconhecida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
