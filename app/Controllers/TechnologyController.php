<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class TechnologyController extends Controller {
    
    public function index() {
        Auth::requirePermission('tecnologia.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $tab = $_GET['tab'] ?? 'cameras';

        $cameras = $pdo->prepare("SELECT * FROM tech_cameras WHERE company_id = ? ORDER BY name");
        $cameras->execute([$companyId]);
        $cameras = $cameras->fetchAll();

        $remotes = $pdo->prepare("SELECT tr.*, u.name as user_name FROM tech_remote_access tr LEFT JOIN users u ON tr.user_id = u.id WHERE tr.company_id = ? ORDER BY u.name");
        $remotes->execute([$companyId]);
        $remotes = $remotes->fetchAll();

        $emails = $pdo->prepare("SELECT te.*, u.name as user_name FROM tech_emails te LEFT JOIN users u ON te.remote_user_id = u.id WHERE te.company_id = ? ORDER BY te.email");
        $emails->execute([$companyId]);
        $emails = $emails->fetchAll();

        $noteSections = $pdo->prepare("SELECT * FROM tech_note_sections WHERE company_id = ? ORDER BY name");
        $noteSections->execute([$companyId]);
        $noteSections = $noteSections->fetchAll();

        $users = $pdo->prepare("SELECT id, name FROM users WHERE company_id = ? ORDER BY name");
        $users->execute([$companyId]);
        $users = $users->fetchAll();

        return $this->view('tecnologia', [
            'cameras' => $cameras,
            'remotes' => $remotes,
            'emails' => $emails,
            'noteSections' => $noteSections,
            'users' => $users,
            'activeTab' => $tab
        ]);
    }

    public function store() {
        Auth::requirePermission('tecnologia.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_camera') {
            $stmt = $pdo->prepare("INSERT INTO tech_cameras (id, company_id, name, ip_address, doc, quantity) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['CAM'.time(), $companyId, $_POST['name'], $_POST['ip_address'], $_POST['doc'], $_POST['quantity']]);
            return $this->redirect('/tecnologia?tab=cameras&success=1');
        }

        if ($action === 'add_remote') {
            $stmt = $pdo->prepare("INSERT INTO tech_remote_access (id, company_id, user_id, pc_password, email_password, pc_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['REM'.time(), $companyId, $_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name']]);
            return $this->redirect('/tecnologia?tab=remotos&success=1');
        }

        return $this->redirect('/tecnologia');
    }
}
