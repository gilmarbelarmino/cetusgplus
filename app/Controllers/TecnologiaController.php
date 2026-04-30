<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Logger;

class TecnologiaController extends Controller {
    
    /**
     * GET /tecnologia
     * Exibe a página principal do módulo de Tecnologia
     */
    public function index() {
        $pdo = Model::getConnection();

        // Registrar acesso ao módulo
        Logger::access('tecnologia');

        // Busca de dados
        $compId = \App\Core\Auth::companyId();
        
        $cameras_stmt = $pdo->prepare("SELECT * FROM tech_cameras WHERE company_id = ? ORDER BY name");
        $cameras_stmt->execute([$compId]);
        $cameras = $cameras_stmt->fetchAll();

        $remotes_stmt = $pdo->prepare("SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email FROM tech_remote_access tr LEFT JOIN users u ON tr.user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci WHERE tr.company_id = ? AND (u.company_id = ? OR u.company_id IS NULL) ORDER BY u.name");
        $remotes_stmt->execute([$compId, $compId]);
        $remotes = $remotes_stmt->fetchAll();

        $emails_stmt = $pdo->prepare("SELECT te.*, u.name as user_name FROM tech_emails te LEFT JOIN users u ON te.remote_user_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci WHERE te.company_id = ? AND (u.company_id = ? OR u.company_id IS NULL) ORDER BY te.email");
        $emails_stmt->execute([$compId, $compId]);
        $emails = $emails_stmt->fetchAll();

        $all_users_stmt = $pdo->prepare("SELECT id, name, avatar_url FROM users WHERE company_id = ? ORDER BY name");
        $all_users_stmt->execute([$compId]);
        $all_users = $all_users_stmt->fetchAll();
        
        // Dados de Anotações
        $note_sections_stmt = $pdo->prepare("SELECT * FROM tech_note_sections WHERE company_id = ? ORDER BY name");
        $note_sections_stmt->execute([$compId]);
        $note_sections = $note_sections_stmt->fetchAll();

        $active_section_id = $_GET['section_id'] ?? ($note_sections[0]['id'] ?? null);
        $notes = [];
        if ($active_section_id) {
            $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE section_id = ? AND company_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$active_section_id, $compId]);
            $notes = $stmt->fetchAll();
        }
        
        $active_note_id = $_GET['note_id'] ?? null;
        $active_note = null;
        if ($active_note_id) {
            $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE id = ? AND company_id = ?");
            $stmt->execute([$active_note_id, $compId]);
            $active_note = $stmt->fetch();
        }

        $activeTab = $_GET['tab'] ?? 'cameras';
        $tech_pass_stmt = $pdo->prepare("SELECT tech_password FROM company_settings WHERE id = ?");
        $tech_pass_stmt->execute([$compId]);
        $tech_pass = $tech_pass_stmt->fetchColumn() ?: '1968';

        // Renderizar a View com todos os dados
        return $this->view('tecnologia', [
            'pdo' => $pdo,
            'cameras' => $cameras,
            'remotes' => $remotes,
            'emails' => $emails,
            'all_users' => $all_users,
            'note_sections' => $note_sections,
            'notes' => $notes,
            'active_section_id' => $active_section_id,
            'active_note_id' => $active_note_id,
            'active_note' => $active_note,
            'activeTab' => $activeTab,
            'tech_pass' => $tech_pass
        ]);
    }

    /**
     * POST /tecnologia
     * Processa todas as ações de formulário do módulo
     */
    public function store() {
        $pdo = Model::getConnection();
        $compId = \App\Core\Auth::companyId();

        // === CÂMERAS ===
        if ($action === 'add_camera') {
            $stmt = $pdo->prepare("INSERT INTO tech_cameras (name, quantity, ip_address, doc, company_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc'], $compId]);
            Logger::audit('add_camera', 'tecnologia', 'Câmera: ' . $_POST['name']);
            return $this->redirect('/tecnologia?tab=cameras&success=1');
        }
        if ($action === 'edit_camera') {
            $stmt = $pdo->prepare("UPDATE tech_cameras SET name = ?, quantity = ?, ip_address = ?, doc = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc'], $_POST['id'], $compId]);
            Logger::audit('edit_camera', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=cameras&success=2');
        }
        if ($action === 'delete_camera') {
            $stmt = $pdo->prepare("DELETE FROM tech_cameras WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['id'], $compId]);
            Logger::audit('delete_camera', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=cameras&success=3');
        }

        // === ACESSOS REMOTOS ===
        if ($action === 'add_remote') {
            $stmt = $pdo->prepare("INSERT INTO tech_remote_access (user_id, pc_password, email_password, pc_name, observations, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations'], $compId]);
            Logger::audit('add_remote', 'tecnologia', 'User ID: ' . $_POST['user_id']);
            return $this->redirect('/tecnologia?tab=remotos&success=4');
        }
        if ($action === 'edit_remote') {
            $stmt = $pdo->prepare("UPDATE tech_remote_access SET user_id = ?, pc_password = ?, email_password = ?, pc_name = ?, observations = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations'], $_POST['id'], $compId]);
            Logger::audit('edit_remote', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=remotos&success=5');
        }
        if ($action === 'delete_remote') {
            $stmt = $pdo->prepare("DELETE FROM tech_remote_access WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['id'], $compId]);
            Logger::audit('delete_remote', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=remotos&success=6');
        }

        // === E-MAILS ===
        if ($action === 'add_email') {
            $stmt = $pdo->prepare("INSERT INTO tech_emails (email, password, type, remote_user_id, usage_date, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date'], $compId]);
            Logger::audit('add_email', 'tecnologia', 'Email: ' . $_POST['email']);
            return $this->redirect('/tecnologia?tab=emails&success=7');
        }
        if ($action === 'edit_email') {
            $stmt = $pdo->prepare("UPDATE tech_emails SET email = ?, password = ?, type = ?, remote_user_id = ?, usage_date = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date'], $_POST['id'], $compId]);
            Logger::audit('edit_email', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=emails&success=8');
        }
        if ($action === 'delete_email') {
            $stmt = $pdo->prepare("DELETE FROM tech_emails WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['id'], $compId]);
            Logger::audit('delete_email', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=emails&success=9');
        }

        // === ANOTAÇÕES (Seções) ===
        if ($action === 'add_note_section') {
            $stmt = $pdo->prepare("INSERT INTO tech_note_sections (name, color, icon, company_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon'], $compId]);
            Logger::audit('add_note_section', 'tecnologia', 'Seção: ' . $_POST['name']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=10');
        }
        if ($action === 'edit_note_section') {
            $stmt = $pdo->prepare("UPDATE tech_note_sections SET name = ?, color = ?, icon = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon'], $_POST['id'], $compId]);
            Logger::audit('edit_note_section', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=11');
        }
        if ($action === 'delete_note_section') {
            $stmt = $pdo->prepare("DELETE FROM tech_note_sections WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['id'], $compId]);
            Logger::audit('delete_note_section', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=12');
        }

        // === ANOTAÇÕES (Páginas/Notas) ===
        if ($action === 'save_note') {
            // Processar imagens Base64 e salvar em disco
            $content = \App\Core\Uploader::processHtmlBase64($_POST['content'], 'editor');

            if (!empty($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE tech_notes SET title = ?, content = ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$_POST['title'], $content, $_POST['id'], $compId]);
                Logger::audit('edit_note', 'tecnologia', 'ID: ' . $_POST['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tech_notes (section_id, title, content, company_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['section_id'], $_POST['title'], $content, $compId]);
                Logger::audit('add_note', 'tecnologia', 'Título: ' . $_POST['title']);
            }
            return $this->redirect('/tecnologia?tab=anotacoes&section_id=' . $_POST['section_id'] . '&success=13');
        }
        if ($action === 'delete_note') {
            $stmt = $pdo->prepare("DELETE FROM tech_notes WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['id'], $compId]);
            Logger::audit('delete_note', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&section_id=' . $_POST['section_id'] . '&success=14');
        }

        // Fallback
        return $this->redirect('/tecnologia');
    }
}
