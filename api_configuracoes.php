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

// Auto-migrations
try { $pdo->exec("ALTER TABLE company_settings ADD COLUMN certificate_signature_name VARCHAR(255) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS rh_positions (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e) {}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $_GET['action'] ?? $json_data['action'] ?? '';
    
    if (!$action && isset($json_data['action'])) {
        $_POST = $json_data;
    }

    $compId = getCurrentUserCompanyId();
    if ($method === 'GET' && empty($action)) {
        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$compId]);
        $units = $units->fetchAll(PDO::FETCH_ASSOC);

        $sectors = $pdo->prepare("SELECT s.*, u.name as unit_name FROM sectors s LEFT JOIN units u ON BINARY s.unit_id = BINARY u.id WHERE s.company_id = ? ORDER BY s.name");
        $sectors->execute([$compId]);
        $sectors = $sectors->fetchAll(PDO::FETCH_ASSOC);

        $positions = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
        $positions->execute([$compId]);
        $positions = $positions->fetchAll(PDO::FETCH_ASSOC);

        $company = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
        $company->execute([$compId]);
        $company = $company->fetch(PDO::FETCH_ASSOC);

        $logs = $pdo->prepare("SELECT * FROM login_logs WHERE company_id = ? ORDER BY login_at DESC LIMIT 100");
        $logs->execute([$compId]);
        $logs = $logs->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => ['units' => $units, 'sectors' => $sectors, 'positions' => $positions, 'company' => $company, 'logs' => $logs]]);
        exit;
    }

    if ($method === 'POST') {
        if ($action === 'save_company') {
            $logo_url = $_POST['current_logo'] ?? $json_data['current_logo'] ?? null;
            if (!empty($_FILES['logo']['name'])) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $filename = 'company_logo.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $logo_url = 'uploads/' . $filename;
                }
            }

            $sig_url = $_POST['current_signature'] ?? $json_data['current_signature'] ?? null;
            if (!empty($_FILES['signature']['name'])) {
                $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
                $filename = 'certificate_signature.' . $ext;
                if (move_uploaded_file($_FILES['signature']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $sig_url = 'uploads/' . $filename;
                }
            }

            $announcement_image_url = $_POST['current_announcement_image'] ?? $json_data['current_announcement_image'] ?? null;
            if (!empty($_FILES['announcement_image']['name'])) {
                $ext = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
                $filename = 'announcement_image_' . time() . '.' . $ext;
                if (!is_dir(__DIR__ . '/uploads/announcements/')) mkdir(__DIR__ . '/uploads/announcements/', 0777, true);
                if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], __DIR__ . '/uploads/announcements/' . $filename)) {
                    $announcement_image_url = 'uploads/announcements/' . $filename;
                }
            }

            $company_name = $_POST['company_name'] ?? $json_data['company_name'] ?? 'CETUSG';
            $sig_name = $_POST['certificate_signature_name'] ?? $json_data['certificate_signature_name'] ?? '';
            $announcement = $_POST['login_announcement'] ?? $json_data['login_announcement'] ?? '';

            $pdo->prepare("UPDATE company_settings SET company_name = ?, logo_url = ?, certificate_signature_url = ?, certificate_signature_name = ?, login_announcement = ?, announcement_image_url = ? WHERE id = ?")
                ->execute([$company_name, $logo_url, $sig_url, $sig_name, $announcement, $announcement_image_url, $compId]);
            
            triggerSocketUpdate('config_updated', ['type' => 'company']);
            echo json_encode(['success' => true, 'message' => 'Configurações atualizadas']);
            exit;
        }

        if ($action === 'update_tech_password') {
            $pdo->prepare("UPDATE company_settings SET tech_password = ? WHERE id = ?")->execute([$json_data['tech_password'] ?? $_POST['tech_password'], $compId]);
            echo json_encode(['success' => true, 'message' => 'Senha da tecnologia atualizada']);
            exit;
        }

        if ($action === 'add_unit') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("INSERT INTO units (id, name, address, cnpj, responsible_name, contact, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['U' . time(), $data['name'], $data['address'], $data['cnpj'], $data['responsible_name'], $data['contact'], $compId]);
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'edit_unit') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("UPDATE units SET name = ?, address = ?, cnpj = ?, responsible_name = ?, contact = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['name'], $data['address'], $data['cnpj'], $data['responsible_name'], $data['contact'], $data['unit_id'], $compId]);
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'delete_unit') {
            $pdo->prepare("DELETE FROM units WHERE id = ? AND company_id = ?")->execute([$_POST['unit_id'] ?? $json_data['unit_id'], $compId]);
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'add_sector') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("INSERT INTO sectors (id, name, unit_id, company_id) VALUES (?, ?, ?, ?)");
            $stmt->execute(['S' . time(), $data['sector_name'], $data['unit_id'], $compId]);
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'add_position') {
            $data = $json_data ?? $_POST;
            $stmt = $pdo->prepare("INSERT INTO rh_positions (name, company_id) VALUES (?, ?)");
            $stmt->execute([$data['position_name'], $compId]);
            echo json_encode(['success' => true]); exit;
        }

        if ($action === 'delete_position') {
            $pdo->prepare("DELETE FROM rh_positions WHERE id = ? AND company_id = ?")->execute([$_POST['position_id'] ?? $json_data['position_id'], $compId]);
            echo json_encode(['success' => true]); exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
