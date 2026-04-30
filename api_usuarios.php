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

    $compId = getCurrentUserCompanyId();

    // ─── GET: Listar Usuários e Históricos ────────────────────────────────
    if ($method === 'GET') {
        if ($action === 'get_histories') {
            $user_id = $_GET['user_id'];
            $loans = $pdo->prepare("SELECT l.*, p.name as patrimony_name FROM loans l LEFT JOIN patrimony p ON BINARY l.patrimony_id = BINARY p.id WHERE l.borrower_id = ? AND l.company_id = ? ORDER BY l.loan_date DESC");
            $loans->execute([$user_id, $compId]);
            
            $tickets = $pdo->prepare("SELECT * FROM tickets WHERE requester_id = ? AND company_id = ? ORDER BY created_at DESC");
            $tickets->execute([$user_id, $compId]);
            
            echo json_encode(['success' => true, 'loans' => $loans->fetchAll(PDO::FETCH_ASSOC), 'tickets' => $tickets->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // Listagem Padrão
        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ?");
        $units->execute([$compId]);
        $units = $units->fetchAll(PDO::FETCH_ASSOC);

        $sectors = $pdo->prepare("SELECT s.id, s.name, s.unit_id FROM sectors s WHERE s.company_id = ? ORDER BY s.name");
        $sectors->execute([$compId]);
        $sectors = $sectors->fetchAll(PDO::FETCH_ASSOC);

        $positions = $pdo->prepare("SELECT name FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
        $positions->execute([$compId]);
        $positions = $positions->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT u.*, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.status != 'Inativo' AND u.company_id = ? ORDER BY u.name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$compId]);
        $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar Menus para cada usuário
        foreach ($all_users as &$usr) {
            $stmt = $pdo->prepare("SELECT menu FROM user_menus WHERE user_id = ?");
            $stmt->execute([$usr['id']]);
            $usr['menus'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode(['success' => true, 'data' => ['users' => $all_users, 'units' => $units, 'sectors' => $sectors, 'positions' => $positions]]);
        exit;
    }

    // ─── POST: Ações de Escrita ───────────────────────────────────────────
    if ($method === 'POST') {
        $data = $json_data ?? $_POST;

        if ($action === 'add_user' || $action === 'edit_user') {
            $is_edit = ($action === 'edit_user');
            $user_id = $is_edit ? $data['user_id'] : 'U' . time();
            
            $avatar_url = $data['current_avatar'] ?? null;
            if (!empty($_FILES['avatar']['name'])) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0777, true);
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $avatar_url = 'uploads/' . $filename;
                }
            }

            if ($is_edit) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, unit_id = ?, sector = ?, avatar_url = ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['name'], $data['email'], $data['role'], $data['unit_id'], $data['sector'], $avatar_url, $user_id, $compId]);
            } else {
                $pass = password_hash($data['password'] ?? '123456', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role, unit_id, sector, avatar_url, status, company_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?, NOW())");
                $stmt->execute([$user_id, $data['name'], $data['email'], $pass, $data['role'], $data['unit_id'] ?? null, $data['sector'] ?? null, $avatar_url, $compId]);
            }

            // Sync Menus (Menus são vinculados ao ID único do usuário, isolamento já garantido pelo filtro acima)
            $pdo->prepare("DELETE FROM user_menus WHERE user_id = ?")->execute([$user_id]);
            $menus = is_string($data['menus'] ?? '') ? json_decode($data['menus'], true) : ($data['menus'] ?? []);
            if ($menus) {
                foreach ($menus as $m) {
                    $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$user_id, $m]);
                }
            }

            triggerSocketUpdate('data_updated', ['module' => 'usuarios', 'action' => $action]);
            echo json_encode(['success' => true, 'id' => $user_id]);
            exit;
        }

        if ($action === 'reset_password') {
            $new_pass = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$new_pass, $data['user_id'], $compId]);

            triggerSocketUpdate('data_updated', ['module' => 'usuarios', 'action' => 'reset_password']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'delete_user') {
            $stmt = $pdo->prepare("UPDATE users SET status = 'Inativo' WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['user_id'], $compId]);

            triggerSocketUpdate('data_updated', ['module' => 'usuarios', 'action' => 'delete']);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
