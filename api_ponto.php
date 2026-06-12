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

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return null;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    
    $action = $data['action'] ?? '';
    
    if ($action === 'register_punch') {
        $type        = $data['type'] ?? 'Auto';
        $lat         = $data['latitude'] ?? null;
        $lng         = $data['longitude'] ?? null;
        $accuracy    = $data['accuracy'] ?? null;
        $address     = $data['address'] ?? '';
        $justification = $data['justification'] ?? null;
        $device      = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip          = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip          = trim(explode(',', $ip)[0]); // pegar IP real atrás de proxy

        if ($type === 'Auto') {
            $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE()");
            $stmt_count->execute([$user['id'], $compId]);
            $count = $stmt_count->fetchColumn();
            
            if ($count == 0) $type = 'Entrada';
            elseif ($count == 1) $type = 'Saida Almoco';
            elseif ($count == 2) $type = 'Retorno Almoco';
            elseif ($count % 2 == 1) $type = 'Saida';
            else $type = 'Entrada';
        }

        $stmt = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
        $stmt->execute([$compId]);
        $comp = $stmt->fetch();

        $status   = 'Aprovado';
        $incident = null;
        $distance = null;

        if ($comp && $comp['latitude'] && $comp['longitude'] && !$comp['allow_remote_work']) {
            $distance = getDistanceMeters($lat, $lng, $comp['latitude'], $comp['longitude']);
            if ($distance !== null && $distance > ($comp['radius_meters'] ?: 100)) {
                $status   = 'Ocorrencia';
                $incident = 'Fora do Raio';
            }
        }

        // Garantir colunas antigas caso não existam no schema, mas não forçamos mais uso facial
        try { $pdo->exec("ALTER TABLE time_records ADD COLUMN gps_accuracy FLOAT DEFAULT NULL"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_records ADD COLUMN facial_used TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_records ADD COLUMN is_manual TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE time_records ADD COLUMN justification TEXT DEFAULT NULL"); } catch(Exception $e){}

        $stmt = $pdo->prepare("INSERT INTO time_records (user_id, company_id, record_type, record_time, latitude, longitude, address, ip_address, device_info, status, gps_accuracy, facial_used, is_manual, justification) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, 0, 1, ?)");
        $stmt->execute([$user['id'], $compId, $type, $lat, $lng, $address, $ip, $device, $status, $accuracy, $justification]);

        $record_id = $pdo->lastInsertId();

        if ($incident) {
            $stmt = $pdo->prepare("INSERT INTO time_incidents (user_id, company_id, record_id, incident_date, incident_type, description) VALUES (?, ?, ?, CURDATE(), ?, ?)");
            $stmt->execute([$user['id'], $compId, $record_id, 'Fora do Raio', "Registro efetuado a " . round($distance, 2) . " metros da empresa."]);
        }

        $overtime_alert = null;
        try {
            $stmt_rh = $pdo->prepare("SELECT daily_work_hours, allow_overtime, overtime_message FROM rh_employee_details WHERE user_id = ? AND company_id = ?");
            $stmt_rh->execute([$user['id'], $compId]);
            $rhData = $stmt_rh->fetch();
            
            if ($rhData && $rhData['allow_overtime'] === 'Não' && !empty($rhData['overtime_message'])) {
                $stmt_rec = $pdo->prepare("SELECT record_time FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE() ORDER BY record_time ASC");
                $stmt_rec->execute([$user['id'], $compId]);
                $punches = $stmt_rec->fetchAll(PDO::FETCH_COLUMN);
                
                $worked_seconds = 0;
                for ($i = 0; $i < count($punches); $i += 2) {
                    if (isset($punches[$i + 1])) {
                        $worked_seconds += strtotime($punches[$i + 1]) - strtotime($punches[$i]);
                    } else {
                        // Current ongoing punch
                        $worked_seconds += time() - strtotime($punches[$i]);
                    }
                }
                
                $daily_target_parts = explode(':', $rhData['daily_work_hours'] ?? '08:00:00');
                $daily_target_seconds = 0;
                if(count($daily_target_parts) >= 2) {
                    $daily_target_seconds = ($daily_target_parts[0] * 3600) + ($daily_target_parts[1] * 60);
                }
                
                if ($daily_target_seconds > 0 && $worked_seconds > $daily_target_seconds) {
                    $overtime_alert = $rhData['overtime_message'];
                }
            }
        } catch(Exception $e) {}

        echo json_encode(['success' => true, 'record_id' => $record_id, 'status' => $status, 'overtime_alert' => $overtime_alert]);
        exit;
    }
}
