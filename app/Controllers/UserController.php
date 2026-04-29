<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Uploader;

class UserController extends Controller {
    
    public function index() {
        Auth::requirePermission('usuarios.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $search = $_GET['search'] ?? '';
        $unit_filter = $_GET['unit'] ?? '';

        // Buscar unidades e cargos para os filtros/formulários
        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ?");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        $positions = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
        $positions->execute([$companyId]);
        $positions = $positions->fetchAll();

        $sectors = $pdo->prepare("SELECT s.id, s.name, s.unit_id FROM sectors s WHERE s.company_id = ? ORDER BY s.name");
        $sectors->execute([$companyId]);
        $sectors = $sectors->fetchAll();

        // Buscar usuários
        $query = "SELECT u.*, un.name as unit_name, rh.role_name as rh_role_name, rh.gender as rh_gender 
                  FROM users u 
                  LEFT JOIN units un ON u.unit_id = un.id 
                  LEFT JOIN rh_employee_details rh ON u.id = rh.user_id 
                  WHERE u.company_id = ?";
        $params = [$companyId];

        if ($search) {
            $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.sector LIKE ? OR u.access_number LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($unit_filter) {
            $query .= " AND u.unit_id = ?";
            $params[] = $unit_filter;
        }
        $query .= " ORDER BY u.name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        // Agrupar por setor para a visualização
        $sectors_list = [];
        foreach ($users as $usr) {
            $sectors_list[$usr['sector'] ?: 'Sem Setor'][] = $usr;
        }

        return $this->view('usuarios', [
            'users' => $users,
            'sectors_list' => $sectors_list,
            'units' => $units,
            'all_positions' => $positions,
            'sectors' => $sectors,
            'search' => $search,
            'unit_filter' => $unit_filter
        ]);
    }

    public function store() {
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_user') {
            Auth::requirePermission('usuarios.create');
            
            // Validações básicas
            if ($this->exists('users', 'login_name', $_POST['login_name'])) {
                return $this->redirect('/usuarios?error=login_exists');
            }

            $new_id = 'U' . time() . rand(100,999);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $avatar_url = null;
            if (!empty($_FILES['avatar']['name'])) {
                try {
                    $avatar_url = Uploader::upload($_FILES['avatar'], 'avatars');
                } catch (\Exception $e) { /* Fallback ou log */ }
            }

            $stmt = $pdo->prepare("INSERT INTO users (id, company_id, name, email, sector, unit_id, role, phone, login_name, password, avatar_url, gender, position, status, access_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?)");
            $stmt->execute([
                $new_id, $companyId, $_POST['name'], $_POST['email'], $_POST['sector'], 
                $_POST['unit_id'], $_POST['role'], $_POST['phone'], $_POST['login_name'], 
                $password, $avatar_url, $_POST['gender'], $_POST['position'], $_POST['access_number']
            ]);

            Logger::audit('create_user', 'usuarios', 'Usuário criado: ' . $_POST['name']);
            return $this->redirect('/usuarios?success=1');
        }

        if ($action === 'edit_user') {
            Auth::requirePermission('usuarios.edit');
            
            $user_id = $_POST['user_id'];
            $avatar_url = $_POST['current_avatar'] ?? null;
            
            if (!empty($_FILES['avatar']['name'])) {
                try {
                    $avatar_url = Uploader::upload($_FILES['avatar'], 'avatars');
                } catch (\Exception $e) { }
            }

            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, sector = ?, unit_id = ?, role = ?, phone = ?, avatar_url = ?, gender = ?, position = ?, access_number = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([
                $_POST['name'], $_POST['email'], $_POST['sector'], $_POST['unit_id'], 
                $_POST['role'], $_POST['phone'], $avatar_url, $_POST['gender'], 
                $_POST['position'], $_POST['access_number'], $user_id, $companyId
            ]);

            Logger::audit('edit_user', 'usuarios', 'Usuário editado: ' . $user_id);
            return $this->redirect('/usuarios?success=2');
        }

        if ($action === 'delete_user') {
            Auth::requirePermission('usuarios.delete');
            $del_id = $_POST['user_id'];

            if ($del_id !== $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
                $stmt->execute([$del_id, $companyId]);
                Logger::audit('delete_user', 'usuarios', 'ID: ' . $del_id);
            }

            return $this->redirect('/usuarios?success=4');
        }

        if ($action === 'reset_password') {
            Auth::requirePermission('usuarios.edit');
            $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, login_name = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$new_password, $_POST['new_login'], $_POST['user_id'], $companyId]);
            
            Logger::audit('reset_password', 'usuarios', 'ID: ' . $_POST['user_id']);
            return $this->redirect('/usuarios?success=3');
        }

        return $this->redirect('/usuarios');
    }

    private function exists($table, $field, $value) {
        $pdo = Model::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $field = ?");
        $stmt->execute([$value]);
        return $stmt->fetchColumn() > 0;
    }
}
