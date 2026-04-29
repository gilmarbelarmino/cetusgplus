<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Uploader;

class ConfigController extends Controller {
    
    public function index() {
        Auth::requirePermission('configuracoes.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $company = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
        $company->execute([$companyId]);
        $company = $company->fetch();

        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        $sectors = $pdo->prepare("SELECT s.*, u.name as unit_name FROM sectors s LEFT JOIN units u ON s.unit_id = u.id WHERE s.company_id = ? ORDER BY s.name");
        $sectors->execute([$companyId]);
        $sectors = $sectors->fetchAll();

        $positions = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name");
        $positions->execute([$companyId]);
        $positions = $positions->fetchAll();

        return $this->view('configuracoes', [
            'company' => $company,
            'units' => $units,
            'sectors' => $sectors,
            'positions' => $positions
        ]);
    }

    public function store() {
        Auth::requirePermission('configuracoes.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'save_company') {
            $logo_url = $_POST['current_logo'] ?? null;
            if (!empty($_FILES['logo']['name'])) {
                try { $logo_url = Uploader::upload($_FILES['logo'], 'company'); } catch (\Exception $e) {}
            }

            $stmt = $pdo->prepare("UPDATE companies SET name = ?, logo_url = ?, certificate_signature_url = ?, certificate_global_text = ? WHERE id = ?");
            $stmt->execute([
                $_POST['company_name'], $logo_url, 
                $_POST['certificate_signature_url'] ?? null, 
                $_POST['certificate_global_text'] ?? '',
                $companyId
            ]);
            
            Logger::audit('save_config', 'config', 'Configurações da empresa atualizadas');
            return $this->redirect('/configuracoes?success=1');
        }

        if ($action === 'add_unit') {
            $stmt = $pdo->prepare("INSERT INTO units (id, company_id, name, address, cnpj) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['U'.time(), $companyId, $_POST['name'], $_POST['address'], $_POST['cnpj']]);
            return $this->redirect('/configuracoes?success=unit');
        }

        return $this->redirect('/configuracoes');
    }
}
