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

try {
    // 1. Relatório Geral de Funcionários com detalhes de RH
    $query = "
        SELECT 
            u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
            un.name as unit_name,
            rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date 
        FROM users u
        LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id
        LEFT JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id
        ORDER BY u.sector ASC, u.name ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Outras tabelas vitais do RH
    $vacations = $pdo->query("SELECT * FROM rh_vacations ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    $certificates = $pdo->query("SELECT * FROM rh_certificates ORDER BY issue_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    $notes = [];
    try { $notes = $pdo->query("SELECT * FROM rh_notes ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e){}

    $announcements = [];
    try { 
        $announcements = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as views FROM announcements a ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC); 
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
                $user = $_POST['created_by'] ?? $json_data['created_by'] ?? 'Sistema';
                
                $stmt = $pdo->prepare("INSERT INTO announcements (message, created_by, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$msg, $user]);
                
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
