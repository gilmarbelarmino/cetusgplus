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

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $_GET['action'] ?? $json_data['action'] ?? '';
    $current_id = $_POST['user_id'] ?? $_GET['user_id'] ?? $json_data['user_id'] ?? null;

    // ─── GET: Listar ou Histórico ──────────────────────────────────────────
    if ($method === 'GET') {
        if ($action === 'list' || empty($action)) {
            $volunteers = $pdo->query("SELECT v.*, u.name as unit_name FROM volunteers v LEFT JOIN units u ON BINARY v.unit_id = BINARY u.id ORDER BY v.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            $units = $pdo->query("SELECT * FROM units ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            $users = $pdo->query("SELECT u.id, u.name, u.email, u.phone, u.sector, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id ORDER BY u.name")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => [
                'volunteers' => $volunteers,
                'units' => $units,
                'users' => $users
            ]]);
            exit;
        }

        if ($action === 'history') {
            $vid = $_GET['volunteer_id'] ?? '';
            $stmt = $pdo->prepare("SELECT vh.*, u.name as editor_name, u.avatar_url as editor_avatar 
                                FROM volunteer_history vh 
                                LEFT JOIN users u ON BINARY vh.edited_by = BINARY u.id 
                                WHERE vh.volunteer_id = ? ORDER BY vh.created_at DESC");
            $stmt->execute([$vid]);
            echo json_encode(['success' => true, 'history' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }
        
        if ($action === 'get_settings') {
            $company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'company' => $company]);
            exit;
        }
    }

    // ─── POST: Ações de Escrita ────────────────────────────────────────────
    if ($method === 'POST') {
        $data = $json_data ?? $_POST;

        if ($action === 'add_volunteer') {
            $vid = 'V' . time();
            $total_hours = 0;
            $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            foreach ($months as $m) { $total_hours += floatval($data["hours_$m"] ?? 0); }

            $avatar_url = null;
            if (isset($_FILES['avatar'])) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_vol_' . $vid . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $avatar_url = 'uploads/' . $filename;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO volunteers (id, name, cpf, avatar_url, gender, email, phone, unit_id, sector_id, volunteering_sector, location, profession, hourly_rate, start_date, work_area, 
                hours_jan, hours_feb, hours_mar, hours_apr, hours_may, hours_jun, hours_jul, hours_aug, hours_sep, hours_oct, hours_nov, hours_dec, 
                total_hours, points, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?,?,?,?,?,?,?,?,?,?,?,?, ?,?,'Ativo')");
            
            $stmt->execute([
                $vid, $data['name'], $data['cpf'] ?? null, $avatar_url, $data['gender'], $data['email'], $data['phone'] ?? null, $data['unit_id'] ?? null, $data['sector_id'] ?? null, $data['volunteering_sector'], 
                $data['location'], $data['profession'], floatval($data['hourly_rate'] ?? 0), $data['start_date'], $data['work_area'],
                floatval($data['hours_jan']??0), floatval($data['hours_feb']??0), floatval($data['hours_mar']??0), floatval($data['hours_apr']??0),
                floatval($data['hours_may']??0), floatval($data['hours_jun']??0), floatval($data['hours_jul']??0), floatval($data['hours_aug']??0),
                floatval($data['hours_sep']??0), floatval($data['hours_oct']??0), floatval($data['hours_nov']??0), floatval($data['hours_dec']??0),
                $total_hours, floor($total_hours)
            ]);

            triggerSocketUpdate('data_updated', ['module' => 'voluntariado', 'action' => 'add']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'edit_volunteer') {
            $vid = $data['volunteer_id'];
            $total_hours = 0;
            $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            foreach ($months as $m) { $total_hours += floatval($data["hours_$m"] ?? 0); }

            $avatar_url = $data['current_avatar'] ?? null;
            if (isset($_FILES['avatar'])) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_vol_' . $vid . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $filename)) {
                    $avatar_url = 'uploads/' . $filename;
                }
            }

            $stmt = $pdo->prepare("UPDATE volunteers SET name=?, cpf=?, avatar_url=?, gender=?, email=?, phone=?, volunteering_sector=?, work_area=?, location=?, profession=?, hourly_rate=?,
                hours_jan=?, hours_feb=?, hours_mar=?, hours_apr=?, hours_may=?, hours_jun=?, hours_jul=?, hours_aug=?, hours_sep=?, hours_oct=?, hours_nov=?, hours_dec=?,
                total_hours=?, points=?, last_edited_by=?, last_edited_at=NOW() WHERE id=?");
            
            $stmt->execute([
                $data['name'], $data['cpf'] ?? null, $avatar_url, $data['gender'], $data['email'], $data['phone'] ?? null, $data['volunteering_sector'], $data['work_area'], $data['location'], $data['profession'], floatval($data['hourly_rate'] ?? 0),
                floatval($data['hours_jan']??0), floatval($data['hours_feb']??0), floatval($data['hours_mar']??0), floatval($data['hours_apr']??0),
                floatval($data['hours_may']??0), floatval($data['hours_jun']??0), floatval($data['hours_jul']??0), floatval($data['hours_aug']??0),
                floatval($data['hours_sep']??0), floatval($data['hours_oct']??0), floatval($data['hours_nov']??0), floatval($data['hours_dec']??0),
                $total_hours, floor($total_hours), $current_id, $vid
            ]);

            triggerSocketUpdate('data_updated', ['module' => 'voluntariado', 'action' => 'edit']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'inativar') {
            $vid = $data['volunteer_id'];
            $vol = $pdo->prepare("SELECT * FROM volunteers WHERE id = ?");
            $vol->execute([$vid]);
            $vol = $vol->fetch(PDO::FETCH_ASSOC);
            if ($vol) {
                $pdo->prepare("INSERT INTO volunteer_history (volunteer_id, start_date, end_date, total_hours, edited_by, edited_at) VALUES (?, ?, CURDATE(), ?, ?, NOW())")
                    ->execute([$vid, $vol['start_date'], $vol['total_hours'], $current_id]);
                $pdo->prepare("UPDATE volunteers SET status = 'Inativo', end_date = CURDATE() WHERE id = ?")
                    ->execute([$vid]);
                
                triggerSocketUpdate('data_updated', ['module' => 'voluntariado', 'action' => 'inativar']);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Voluntário não encontrado']);
            }
            exit;
        }

        if ($action === 'reativar') {
            $vid = $data['volunteer_id'];
            $pdo->prepare("UPDATE volunteers SET status = 'Ativo', end_date = NULL, start_date = CURDATE(), total_hours = 0, points = 0,
                hours_jan=0,hours_feb=0,hours_mar=0,hours_apr=0,hours_may=0,hours_jun=0,
                hours_jul=0,hours_aug=0,hours_sep=0,hours_oct=0,hours_nov=0,hours_dec=0,
                last_edited_by = ?, last_edited_at = NOW() WHERE id = ?")
                ->execute([$current_id, $vid]);
            
            $pdo->prepare("INSERT INTO volunteer_history (volunteer_id, start_date, total_hours, edited_by, edited_at) VALUES (?, CURDATE(), 0, ?, NOW())")
                ->execute([$vid, $current_id]);
            
            triggerSocketUpdate('data_updated', ['module' => 'voluntariado', 'action' => 'reativar']);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'excluir') {
            $vid = $data['volunteer_id'];
            $pdo->prepare("DELETE FROM volunteer_history WHERE volunteer_id = ?")->execute([$vid]);
            $pdo->prepare("DELETE FROM volunteers WHERE id = ?")->execute([$vid]);
            
            triggerSocketUpdate('data_updated', ['module' => 'voluntariado', 'action' => 'excluir']);
            echo json_encode(['success' => true]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Ação ou método inválido']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
