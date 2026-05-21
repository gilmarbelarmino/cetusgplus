<?php
header('Content-Type: application/json');
require_once 'config.php';
require_once 'auth.php';

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$compId = getCurrentUserCompanyId();

// Garantir a tabela de faces
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_face_descriptors (
        user_id VARCHAR(50) PRIMARY KEY,
        descriptor LONGTEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'init';
    
    if ($action === 'init') {
        $stmt = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
        $stmt->execute([$compId]);
        $company = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE() ORDER BY record_time ASC");
        $stmt->execute([$user['id'], $compId]);
        $records = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT descriptor FROM user_face_descriptors WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $face_data = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'company' => $company,
            'records' => $records,
            'has_face_registered' => !empty($face_data['descriptor'])
        ]);
        exit;
    }
    
    if ($action === 'get_face_descriptor') {
        $stmt = $pdo->prepare("SELECT descriptor FROM user_face_descriptors WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $data = $stmt->fetch();
        echo json_encode(['success' => true, 'descriptor' => $data ? $data['descriptor'] : null]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    $action = $data['action'] ?? '';
    
    if ($action === 'register_face') {
        $descriptor = $data['descriptor'] ?? '';
        if (!$descriptor) {
            echo json_encode(['success' => false, 'error' => 'Descriptor missing']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO user_face_descriptors (user_id, descriptor) VALUES (?, ?) ON DUPLICATE KEY UPDATE descriptor = ?");
        $stmt->execute([$user['id'], $descriptor, $descriptor]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'register_punch') {
        $type = $data['type'] ?? 'Entrada';
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        $photo = $data['photo'] ?? '';
        $address = $data['address'] ?? '';
        $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $stmt = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
        $stmt->execute([$compId]);
        $comp = $stmt->fetch();
        
        $status = 'Aprovado';
        $incident = null;
        $distance = null;
        
        if ($comp && $comp['latitude'] && $comp['longitude'] && !$comp['allow_remote_work']) {
            $distance = getDistanceMeters($lat, $lng, $comp['latitude'], $comp['longitude']);
            if ($distance !== null && $distance > ($comp['radius_meters'] ?: 100)) {
                $status = 'Ocorrencia';
                $incident = 'Fora do Raio';
            }
        }
        
        $stmt = $pdo->prepare("INSERT INTO time_records (user_id, company_id, record_type, record_time, latitude, longitude, address, ip_address, device_info, photo_base64, status) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user['id'], $compId, $type, $lat, $lng, $address, $ip, $device, $photo, $status]);
        
        $record_id = $pdo->lastInsertId();
        
        if ($incident) {
            $stmt = $pdo->prepare("INSERT INTO time_incidents (user_id, company_id, record_id, incident_date, incident_type, description) VALUES (?, ?, ?, CURDATE(), ?, ?)");
            $stmt->execute([$user['id'], $compId, $record_id, 'Fora do Raio', "Registro efetuado a " . round($distance, 2) . " metros da empresa."]);
        }
        
        echo json_encode(['success' => true, 'record_id' => $record_id, 'status' => $status]);
        exit;
    }
}
