<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Uploader;

class SemanadaController extends Controller {
    
    public function index() {
        Auth::requirePermission('semanada.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $upload = $pdo->prepare("SELECT su.*, u.name as uploader_name FROM semanada_uploads su LEFT JOIN users u ON su.uploaded_by COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci WHERE su.company_id = ? AND su.is_history = 0 ORDER BY su.uploaded_at DESC LIMIT 1");
        $upload->execute([$companyId]);
        $upload = $upload->fetch();

        return $this->view('semanada', [
            'upload' => $upload
        ]);
    }

    public function store() {
        Auth::requirePermission('semanada.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'upload_pdf' && !empty($_FILES['pdf_file']['name'])) {
            // Mover anteriores para histórico
            $pdo->prepare("UPDATE semanada_uploads SET is_history = 1 WHERE company_id = ?")->execute([$companyId]);
            
            try {
                $filename = Uploader::upload($_FILES['pdf_file'], 'semanada');
                $stmt = $pdo->prepare("INSERT INTO semanada_uploads (company_id, filename, original_name, uploaded_by, uploaded_at, is_history) VALUES (?, ?, ?, ?, NOW(), 0)");
                $stmt->execute([$companyId, $filename, $_FILES['pdf_file']['name'], $_SESSION['user_id']]);
                
                Logger::audit('upload_semanada', 'semanada', 'Arquivo: ' . $_FILES['pdf_file']['name']);
                return $this->redirect('/semanada?success=1');
            } catch (\Exception $e) {
                return $this->redirect('/semanada?error=' . urlencode($e->getMessage()));
            }
        }

        return $this->redirect('/semanada');
    }
}
