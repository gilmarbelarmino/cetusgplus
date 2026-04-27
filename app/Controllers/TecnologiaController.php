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
        $cameras = $pdo->query("SELECT * FROM tech_cameras ORDER BY name")->fetchAll();
        $remotes = $pdo->query("SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email FROM tech_remote_access tr LEFT JOIN users u ON tr.user_id = u.id ORDER BY u.name")->fetchAll();
        $emails = $pdo->query("SELECT te.*, u.name as user_name FROM tech_emails te LEFT JOIN users u ON te.remote_user_id = u.id ORDER BY te.email")->fetchAll();
        $all_users = $pdo->query("SELECT id, name, avatar_url FROM users ORDER BY name")->fetchAll();
        
        // Dados de Anotações
        $note_sections = $pdo->query("SELECT * FROM tech_note_sections ORDER BY name")->fetchAll();
        $active_section_id = $_GET['section_id'] ?? ($note_sections[0]['id'] ?? null);
        $notes = [];
        if ($active_section_id) {
            $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE section_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$active_section_id]);
            $notes = $stmt->fetchAll();
        }
        
        $active_note_id = $_GET['note_id'] ?? null;
        $active_note = null;
        if ($active_note_id) {
            $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE id = ?");
            $stmt->execute([$active_note_id]);
            $active_note = $stmt->fetch();
        }

        $activeTab = $_GET['tab'] ?? 'cameras';
        $tech_pass = $pdo->query("SELECT tech_password FROM company_settings WHERE id = 1")->fetchColumn() ?: '1968';

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
        $action = $_POST['action'] ?? '';

        // === CÂMERAS ===
        if ($action === 'add_camera') {
            $stmt = $pdo->prepare("INSERT INTO tech_cameras (name, quantity, ip_address, doc) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc']]);
            Logger::audit('add_camera', 'tecnologia', 'Câmera: ' . $_POST['name']);
            return $this->redirect('/tecnologia?tab=cameras&success=1');
        }
        if ($action === 'edit_camera') {
            $stmt = $pdo->prepare("UPDATE tech_cameras SET name = ?, quantity = ?, ip_address = ?, doc = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc'], $_POST['id']]);
            Logger::audit('edit_camera', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=cameras&success=2');
        }
        if ($action === 'delete_camera') {
            $stmt = $pdo->prepare("DELETE FROM tech_cameras WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            Logger::audit('delete_camera', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=cameras&success=3');
        }

        // === ACESSOS REMOTOS ===
        if ($action === 'add_remote') {
            $stmt = $pdo->prepare("INSERT INTO tech_remote_access (user_id, pc_password, email_password, pc_name, observations) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations']]);
            Logger::audit('add_remote', 'tecnologia', 'User ID: ' . $_POST['user_id']);
            return $this->redirect('/tecnologia?tab=remotos&success=4');
        }
        if ($action === 'edit_remote') {
            $stmt = $pdo->prepare("UPDATE tech_remote_access SET user_id = ?, pc_password = ?, email_password = ?, pc_name = ?, observations = ? WHERE id = ?");
            $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations'], $_POST['id']]);
            Logger::audit('edit_remote', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=remotos&success=5');
        }
        if ($action === 'delete_remote') {
            $stmt = $pdo->prepare("DELETE FROM tech_remote_access WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            Logger::audit('delete_remote', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=remotos&success=6');
        }

        // === E-MAILS ===
        if ($action === 'add_email') {
            $stmt = $pdo->prepare("INSERT INTO tech_emails (email, password, type, remote_user_id, usage_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date']]);
            Logger::audit('add_email', 'tecnologia', 'Email: ' . $_POST['email']);
            return $this->redirect('/tecnologia?tab=emails&success=7');
        }
        if ($action === 'edit_email') {
            $stmt = $pdo->prepare("UPDATE tech_emails SET email = ?, password = ?, type = ?, remote_user_id = ?, usage_date = ? WHERE id = ?");
            $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date'], $_POST['id']]);
            Logger::audit('edit_email', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=emails&success=8');
        }
        if ($action === 'delete_email') {
            $stmt = $pdo->prepare("DELETE FROM tech_emails WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            Logger::audit('delete_email', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=emails&success=9');
        }

        // === ANOTAÇÕES (Seções) ===
        if ($action === 'add_note_section') {
            $stmt = $pdo->prepare("INSERT INTO tech_note_sections (name, color, icon) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon']]);
            Logger::audit('add_note_section', 'tecnologia', 'Seção: ' . $_POST['name']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=10');
        }
        if ($action === 'edit_note_section') {
            $stmt = $pdo->prepare("UPDATE tech_note_sections SET name = ?, color = ?, icon = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon'], $_POST['id']]);
            Logger::audit('edit_note_section', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=11');
        }
        if ($action === 'delete_note_section') {
            $stmt = $pdo->prepare("DELETE FROM tech_note_sections WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            Logger::audit('delete_note_section', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&success=12');
        }

        // === ANOTAÇÕES (Páginas/Notas) ===
        if ($action === 'save_note') {
            // Processar imagens Base64 e salvar em disco
            $content = \App\Core\Uploader::processHtmlBase64($_POST['content'], 'editor');

            if (!empty($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE tech_notes SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$_POST['title'], $content, $_POST['id']]);
                Logger::audit('edit_note', 'tecnologia', 'ID: ' . $_POST['id']);
            } else {
                $stmt = $pdo->prepare("INSERT INTO tech_notes (section_id, title, content) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['section_id'], $_POST['title'], $content]);
                Logger::audit('add_note', 'tecnologia', 'Título: ' . $_POST['title']);
            }
            return $this->redirect('/tecnologia?tab=anotacoes&section_id=' . $_POST['section_id'] . '&success=13');
        }
        if ($action === 'delete_note') {
            $stmt = $pdo->prepare("DELETE FROM tech_notes WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            Logger::audit('delete_note', 'tecnologia', 'ID: ' . $_POST['id']);
            return $this->redirect('/tecnologia?tab=anotacoes&section_id=' . $_POST['section_id'] . '&success=14');
        }

        // Fallback
        return $this->redirect('/tecnologia');
    }
}
