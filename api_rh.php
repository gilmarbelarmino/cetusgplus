<?php
// API para listar dados completos do Módulo RH
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

    $compId = getCurrentUserCompanyId();

    // 1. Relatório Geral de Funcionários com detalhes de RH
    $query = "
        SELECT 
            u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
            un.name as unit_name,
            rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date 
        FROM users u
        LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id
        LEFT JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id
        WHERE u.company_id = ?
        ORDER BY u.sector ASC, u.name ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$compId]);
    $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Outras tabelas vitais do RH
    $vacations = $pdo->prepare("SELECT * FROM rh_vacations WHERE company_id = ? ORDER BY start_date DESC");
    $vacations->execute([$compId]);
    $vacations = $vacations->fetchAll(PDO::FETCH_ASSOC);

    $certificates = $pdo->prepare("SELECT * FROM rh_certificates WHERE company_id = ? ORDER BY issue_date DESC");
    $certificates->execute([$compId]);
    $certificates = $certificates->fetchAll(PDO::FETCH_ASSOC);
    
    $notes = [];
    try { 
        $stmt = $pdo->prepare("SELECT * FROM rh_notes WHERE company_id = ? ORDER BY created_at DESC");
        $stmt->execute([$compId]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch(Exception $e){}

    $announcements = [];
    try { 
        $stmt = $pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as views FROM announcements a WHERE a.company_id = ? ORDER BY a.created_at DESC");
        $stmt->execute([$compId]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch(Exception $e) {}

    echo json_encode([
        'success' => true,
        'data' => [
            'employees' => $users_data,
            'vacations' => $vacations,
            'certificates' => $certificates,
            'notes' => $notes,
            'announcements' => $announcements
        ]
    ]);
    exit;

} catch(Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $json_data = json_decode(file_get_contents('php://input'), true);
            $action = $_POST['action'] ?? $json_data['action'] ?? '';
            
            if ($action === 'add_announcement') {
                $msg = $_POST['message'] ?? $json_data['message'] ?? '';
                $user_name = $_POST['created_by'] ?? $json_data['created_by'] ?? 'Sistema';
                $compId = getCurrentUserCompanyId();
                
                $stmt = $pdo->prepare("INSERT INTO announcements (message, created_by, company_id, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$msg, $user_name, $compId]);
                
                triggerSocketUpdate('data_updated', ['module' => 'rh', 'action' => 'add_announcement']);
                echo json_encode(['success' => true]);
                exit;
            }
        } catch(Exception $ex) {
            echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
