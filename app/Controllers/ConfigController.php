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

        // Buscar configurações da empresa
        $company = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
        $company->execute([$companyId]);
        $company = $company->fetch();

        // Buscar Unidades
        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        // Buscar Setores
        $sectors = $pdo->prepare("SELECT s.*, u.name as unit_name FROM sectors s LEFT JOIN units u ON s.unit_id = u.id WHERE s.company_id = ? ORDER BY s.name");
        $sectors->execute([$companyId]);
        $sectors = $sectors->fetchAll();

        // Buscar Cargos
        $positions = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name");
        $positions->execute([$companyId]);
        $positions = $positions->fetchAll();

        // Buscar Logs de Acesso
        $logs = $pdo->prepare("SELECT * FROM login_logs WHERE company_id = ? ORDER BY login_at DESC LIMIT 100");
        $logs->execute([$companyId]);
        $logs = $logs->fetchAll();

        return $this->view('configuracoes', [
            'company' => $company,
            'units' => $units,
            'sectors' => $sectors,
            'positions' => $positions,
            'logs' => $logs
        ]);
    }

    public function store() {
        Auth::requirePermission('configuracoes.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        // 1. Salvar Identidade e Comunicado
        if ($action === 'save_company') {
            $logo_url = $_POST['current_logo'] ?? null;
            if (!empty($_FILES['logo']['name'])) {
                try { $logo_url = Uploader::upload($_FILES['logo'], 'company'); } catch (\Exception $e) {}
            }

            $sig_url = $_POST['current_signature'] ?? null;
            if (!empty($_FILES['signature']['name'])) {
                try { $sig_url = Uploader::upload($_FILES['signature'], 'company'); } catch (\Exception $e) {}
            }

            $announcement_image_url = $_POST['current_announcement_image'] ?? null;
            if (!empty($_FILES['announcement_image']['name'])) {
                try { $announcement_image_url = Uploader::upload($_FILES['announcement_image'], 'announcements'); } catch (\Exception $e) {}
            }

            $stmt = $pdo->prepare("UPDATE company_settings SET company_name = ?, logo_url = ?, certificate_signature_url = ?, certificate_global_text = ?, login_announcement = ?, announcement_image_url = ?, backup_full_path = ? WHERE id = ?");
            $stmt->execute([
                $_POST['company_name'], $logo_url, $sig_url, 
                $_POST['certificate_global_text'] ?? '', 
                $_POST['login_announcement'] ?? '',
                $announcement_image_url,
                $_POST['backup_full_path'] ?? '',
                $companyId
            ]);
            
            Logger::audit('save_config', 'config', 'Configurações da empresa atualizadas');
            return $this->redirect('/configuracoes?success=company');
        }

        // 2. Senha Tecnologia
        if ($action === 'update_tech_password') {
            $pdo->prepare("UPDATE company_settings SET tech_password = ? WHERE id = ?")
                ->execute([$_POST['tech_password'], $companyId]);
            return $this->redirect('/configuracoes?success=tech_pass');
        }

        // 3. Mensagens de Aniversário
        if ($action === 'save_birthday_messages') {
            $pdo->prepare("UPDATE company_settings SET birthday_message_all = ?, birthday_message_self = ? WHERE id = ?")
                ->execute([$_POST['birthday_message_all'] ?? '', $_POST['birthday_message_self'] ?? '', $companyId]);
            return $this->redirect('/configuracoes?success=birthday&tab=aniversarios');
        }

        // 4. Unidades
        if ($action === 'add_unit') {
            $stmt = $pdo->prepare("INSERT INTO units (id, company_id, name, address, cnpj, responsible_name, contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(['U'.time(), $companyId, $_POST['name'], $_POST['address'], $_POST['cnpj'], $_POST['responsible_name'], $_POST['contact']]);
            return $this->redirect('/configuracoes?success=unit');
        }

        if ($action === 'edit_unit') {
            $stmt = $pdo->prepare("UPDATE units SET name = ?, address = ?, cnpj = ?, responsible_name = ?, contact = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['name'], $_POST['address'], $_POST['cnpj'], $_POST['responsible_name'], $_POST['contact'], $_POST['unit_id'], $companyId]);
            return $this->redirect('/configuracoes?success=unit_edit');
        }

        if ($action === 'delete_unit') {
            $pdo->prepare("DELETE FROM units WHERE id = ? AND company_id = ?")->execute([$_POST['unit_id'], $companyId]);
            return $this->redirect('/configuracoes?success=unit_del');
        }

        // 5. Setores
        if ($action === 'add_sector') {
            $stmt = $pdo->prepare("INSERT INTO sectors (id, company_id, name, unit_id) VALUES (?, ?, ?, ?)");
            $stmt->execute(['S'.time(), $companyId, $_POST['sector_name'], $_POST['unit_id']]);
            return $this->redirect('/configuracoes?success=sector');
        }

        // 6. Cargos
        if ($action === 'add_position') {
            $stmt = $pdo->prepare("INSERT INTO rh_positions (name, company_id) VALUES (?, ?)");
            $stmt->execute([$_POST['position_name'], $companyId]);
            return $this->redirect('/configuracoes?success=position&tab=cargos');
        }

        if ($action === 'delete_position') {
            $pdo->prepare("DELETE FROM rh_positions WHERE id = ? AND company_id = ?")->execute([$_POST['position_id'], $companyId]);
            return $this->redirect('/configuracoes?success=position_del&tab=cargos');
        }

        return $this->redirect('/configuracoes');
    }
}
