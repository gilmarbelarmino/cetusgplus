<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db_config.php';
require_once __DIR__ . '/app/Services/TechnologyService.php';

use App\Services\TechnologyService;

$service = new TechnologyService($pdo);
$action = $_GET['action'] ?? '';

// Rotas GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list_cameras') {
        echo json_encode($service->listCameras());
        exit;
    }

    if ($action === 'list_remotes') {
        echo json_encode($service->listRemotes());
        exit;
    }

    if ($action === 'list_emails') {
        echo json_encode($service->listEmails());
        exit;
    }
}

// Rotas POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    try {
        switch ($action) {
            case 'save_camera':
                $service->saveCamera($data);
                echo json_encode(['success' => true]);
                break;
            case 'delete_camera':
                $service->deleteCamera($data['id']);
                echo json_encode(['success' => true]);
                break;
            case 'save_remote':
                $service->saveRemote($data);
                echo json_encode(['success' => true]);
                break;
            case 'delete_remote':
                $service->deleteRemote($data['id']);
                echo json_encode(['success' => true]);
                break;
            case 'save_email':
                $service->saveEmail($data);
                echo json_encode(['success' => true]);
                break;
            case 'delete_email':
                $service->deleteEmail($data['id']);
                echo json_encode(['success' => true]);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Ação não reconhecida']);
                break;
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}
?>
