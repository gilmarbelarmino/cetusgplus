<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Uploader;

class RHController extends Controller {
    public function index() {
        Auth::requirePermission('rh.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $search = $_GET['search'] ?? '';
        $query = "
            SELECT 
                u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
                un.name as unit_name,
                rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date 
            FROM users u
            LEFT JOIN units un ON u.unit_id = un.id
            LEFT JOIN rh_employee_details rh ON u.id = rh.user_id
            WHERE u.company_id = ?
        ";
        $params = [$companyId];
        if ($search) {
            $query .= " AND (u.name LIKE ? OR u.sector LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        $query .= " ORDER BY u.sector ASC, u.name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        return $this->view('hr', [
            'users' => $users,
            'search' => $search
        ]);
    }

    public function store() {
        Auth::requirePermission('rh.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'save_contract') {
            $stmt = $pdo->prepare("REPLACE INTO rh_employee_details (user_id, company_id, contract_type, role_name, work_days, work_hours, salary, use_transport, transport_value, gender, birth_date, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['user_id'], $companyId, $_POST['contract_type'], 
                $_POST['role_name'] ?? '', $_POST['work_days'], $_POST['work_hours'], 
                !empty($_POST['salary']) ? $_POST['salary'] : 0, $_POST['use_transport'] ?? 'Não', 
                !empty($_POST['transport_value']) ? $_POST['transport_value'] : 0, $_POST['gender'] ?? '',
                !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                !empty($_POST['start_date']) ? $_POST['start_date'] : null, 
                !empty($_POST['end_date']) ? $_POST['end_date'] : null
            ]);
            
            Logger::audit('save_contract', 'rh', 'Usuário ID: ' . $_POST['user_id']);
            return $this->redirect('/rh?success=contract');
        }

        if ($action === 'add_vacation') {
            $stmt = $pdo->prepare("INSERT INTO rh_vacations (user_id, company_id, reference_year, start_date, end_date, limit_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['user_id'], $companyId, $_POST['reference_year'], 
                $_POST['start_date'], $_POST['end_date'], $_POST['limit_date'], $_POST['status']
            ]);
            return $this->redirect('/rh?success=vacation_added');
        }

        return $this->redirect('/rh');
    }

    public function voluntariado() {
        Auth::requirePermission('rh.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $certVol = null;
        if (isset($_GET['id'])) {
            $s = $pdo->prepare("SELECT v.*, u.name as unit_name FROM volunteers v LEFT JOIN units u ON v.unit_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci WHERE v.id = ? AND v.company_id = ?");
            $s->execute([$_GET['id'], $companyId]);
            $certVol = $s->fetch();
        }

        $volunteers = $pdo->prepare("SELECT v.*, u.name as unit_name FROM volunteers v LEFT JOIN units u ON v.unit_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci WHERE v.company_id = ? ORDER BY v.created_at DESC");
        $volunteers->execute([$companyId]);
        $volunteers = $volunteers->fetchAll();

        $users = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.sector, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id COLLATE utf8mb4_unicode_ci = un.id COLLATE utf8mb4_unicode_ci WHERE u.company_id = ? ORDER BY u.name");
        $users->execute([$companyId]);
        $users = $users->fetchAll();

        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        return $this->view('voluntariado', [
            'volunteers' => $volunteers,
            'certVol' => $certVol,
            'users' => $users,
            'units' => $units
        ]);
    }

    public function storeVoluntariado() {
        Auth::requirePermission('rh.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_volunteer') {
            $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
            $total = 0;
            foreach ($months as $m) { $total += floatval($_POST["hours_$m"] ?? 0); }
            
            $vid = 'V' . time();
            $avatar_url = null;
            if (!empty($_FILES['avatar']['name'])) {
                $avatar_url = Uploader::upload($_FILES['avatar'], 'volunteers');
            }

            $stmt = $pdo->prepare("INSERT INTO volunteers (id, name, cpf, avatar_url, gender, email, phone, unit_id, sector_id, volunteering_sector, location, profession, hourly_rate, start_date, work_area, hours_jan, hours_feb, hours_mar, hours_apr, hours_may, hours_jun, hours_jul, hours_aug, hours_sep, hours_oct, hours_nov, hours_dec, total_hours, points, status, company_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?, NOW())");
            $stmt->execute([
                $vid, $_POST['name'], $_POST['cpf'], $avatar_url, $_POST['gender'] ?? 'Outro', 
                $_POST['email'], $_POST['phone'], $_POST['unit_id'], $_POST['sector_id'], 
                $_POST['volunteering_sector'], implode(', ', $_POST['location'] ?? []), $_POST['profession'], 
                $_POST['hourly_rate'], $_POST['start_date'], $_POST['work_area'],
                floatval($_POST['hours_jan']??0), floatval($_POST['hours_feb']??0), floatval($_POST['hours_mar']??0), floatval($_POST['hours_apr']??0),
                floatval($_POST['hours_may']??0), floatval($_POST['hours_jun']??0), floatval($_POST['hours_jul']??0), floatval($_POST['hours_aug']??0),
                floatval($_POST['hours_sep']??0), floatval($_POST['hours_oct']??0), floatval($_POST['hours_nov']??0), floatval($_POST['hours_dec']??0),
                $total, floor($total), $companyId
            ]);
            
            Logger::audit('add_volunteer', 'rh', 'Voluntário: ' . $_POST['name']);
            return $this->redirect('/rh/voluntariado?success=1');
        }

        if ($action === 'inativar') {
            $vid = $_POST['volunteer_id'];
            $pdo->prepare("UPDATE volunteers SET status = 'Inativo', end_date = CURDATE() WHERE id = ? AND company_id = ?")->execute([$vid, $companyId]);
            Logger::audit('inativar_volunteer', 'rh', 'ID: ' . $vid);
            return $this->redirect('/rh/voluntariado?success=inactivated');
        }

        return $this->redirect('/rh/voluntariado');
    }
}
