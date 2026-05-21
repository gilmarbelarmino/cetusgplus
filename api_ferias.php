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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM vacations WHERE user_id = ? AND company_id = ? ORDER BY start_date DESC");
        $stmt->execute([$user['id'], $compId]);
        $vacations = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("SELECT * FROM medical_certificates WHERE user_id = ? AND company_id = ? ORDER BY issue_date DESC");
        $stmt->execute([$user['id'], $compId]);
        $certificates = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'vacations' => $vacations,
            'certificates' => $certificates
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    $action = $data['action'] ?? '';
    
    if ($action === 'request_vacation') {
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? null;
        
        if (!$start || !$end) {
            echo json_encode(['success' => false, 'error' => 'Datas inválidas.']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO vacations (user_id, company_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'Solicitado')");
        $stmt->execute([$user['id'], $compId, $start, $end]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'upload_certificate') {
        $issue = $data['issue_date'] ?? null;
        $days = (int)($data['days_off'] ?? 0);
        $file = $data['file_base64'] ?? '';
        
        if (!$issue || !$file) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO medical_certificates (user_id, company_id, issue_date, days_off, file_base64, status) VALUES (?, ?, ?, ?, ?, 'Pendente')");
        $stmt->execute([$user['id'], $compId, $issue, $days, $file]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}
