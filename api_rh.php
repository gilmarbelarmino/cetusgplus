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
require_once __DIR__ . '/app/Services/HrService.php';

use App\Services\HrService;

try {
    $compId = getCurrentUserCompanyId();
    $hrService = new HrService($pdo);

    // --- ROTA GET: Busca o Relatório (Dashboard) de RH ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $dashboardData = $hrService->getFullDashboardData($compId);
        
        echo json_encode([
            'success' => true,
            'data' => $dashboardData
        ]);
        exit;
    }

    // --- ROTA POST: Processa as ações de gravação (ex: Mural) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json_data = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = $_POST['action'] ?? $json_data['action'] ?? '';
        
        if ($action === 'add_announcement') {
            $msg = $_POST['message'] ?? $json_data['message'] ?? '';
            $user_name = $_POST['created_by'] ?? $json_data['created_by'] ?? 'Sistema';
            
            $hrService->addAnnouncement($compId, $msg, $user_name);
            
            triggerSocketUpdate('data_updated', ['module' => 'rh', 'action' => 'add_announcement']);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Ação POST não reconhecida ou não enviada.']);
        exit;
    }

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
