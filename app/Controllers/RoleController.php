<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class RoleController extends Controller {
    
    public function index() {
        Auth::requirePermission('configuracoes.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $roles = $pdo->prepare("SELECT * FROM roles WHERE company_id = ? ORDER BY level DESC");
        $roles->execute([$companyId]);
        $roles = $roles->fetchAll();

        $permissions = $pdo->prepare("SELECT * FROM permissions WHERE company_id = ? ORDER BY module, name");
        $permissions->execute([$companyId]);
        $permissions = $permissions->fetchAll();

        // Agrupar permissões por módulo
        $permissions_by_module = [];
        foreach ($permissions as $p) {
            $permissions_by_module[$p['module']][] = $p;
        }

        return $this->view('roles', [
            'roles' => $roles,
            'permissions_by_module' => $permissions_by_module
        ]);
    }

    public function store() {
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'save_role_permissions') {
            Auth::requirePermission('configuracoes.edit');
            $role_id = $_POST['role_id'];
            $permissions = $_POST['permissions'] ?? [];

            $pdo->prepare("DELETE FROM role_permission WHERE role_id = ?")->execute([$role_id]);

            foreach ($permissions as $perm_id) {
                $pdo->prepare("INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)")
                    ->execute([$role_id, $perm_id]);
            }

            Logger::audit('update_role_permissions', 'configuracoes', 'Role ID: ' . $role_id);
            return $this->redirect('/configuracoes/roles?success=1');
        }

        return $this->redirect('/configuracoes/roles');
    }
}
