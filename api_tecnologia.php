<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db_config.php';

$action = $_GET['action'] ?? '';

// --- TABELAS ---
// tech_cameras: id, name, ip_address, quantity
// tech_remote_access: id, user_id, pc_password, email_password, pc_name, observations
// tech_emails: id, email, password, type, remote_user_id, usage_date

if ($action === 'list_cameras') {
    echo json_encode($pdo->query("SELECT * FROM tech_cameras ORDER BY name")->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'list_remotes') {
    $sql = "SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email 
            FROM tech_remote_access tr 
            LEFT JOIN users u ON tr.user_id = u.id 
            ORDER BY u.name";
    echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'list_emails') {
    $sql = "SELECT te.*, u.name as user_name 
            FROM tech_emails te 
            LEFT JOIN users u ON te.remote_user_id = u.id 
            ORDER BY te.email";
    echo json_encode($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    // Cameras
    if ($action === 'save_camera') {
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE tech_cameras SET name = ?, ip_address = ?, quantity = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['ip_address'], $data['quantity'], $data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tech_cameras (name, ip_address, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['ip_address'], $data['quantity']]);
        }
        echo json_encode(['success' => true]);
    }
    
    if ($action === 'delete_camera') {
        $stmt = $pdo->prepare("DELETE FROM tech_cameras WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
    }

    // Remotes
    if ($action === 'save_remote') {
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE tech_remote_access SET user_id = ?, pc_password = ?, email_password = ?, pc_name = ?, observations = ? WHERE id = ?");
            $stmt->execute([$data['user_id'], $data['pc_password'], $data['email_password'], $data['pc_name'], $data['observations'], $data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tech_remote_access (user_id, pc_password, email_password, pc_name, observations) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['user_id'], $data['pc_password'], $data['email_password'], $data['pc_name'], $data['observations']]);
        }
        echo json_encode(['success' => true]);
    }

    if ($action === 'delete_remote') {
        $stmt = $pdo->prepare("DELETE FROM tech_remote_access WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
    }

    // Emails
    if ($action === 'save_email') {
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE tech_emails SET email = ?, password = ?, type = ?, remote_user_id = ?, usage_date = ? WHERE id = ?");
            $stmt->execute([$data['email'], $data['password'], $data['type'], $data['remote_user_id'], $data['usage_date'], $data['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tech_emails (email, password, type, remote_user_id, usage_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['email'], $data['password'], $data['type'], $data['remote_user_id'], $data['usage_date']]);
        }
        echo json_encode(['success' => true]);
    }

    if ($action === 'delete_email') {
        $stmt = $pdo->prepare("DELETE FROM tech_emails WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
    }
    
    exit;
}
?>
