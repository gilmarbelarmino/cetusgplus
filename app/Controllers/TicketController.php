<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class TicketController extends Controller {
    
    public function index() {
        Auth::requirePermission('chamados.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $show_all = isset($_GET['all']) && $_GET['all'] == '1';
        $conditions = ["t.company_id = ?"];
        $params = [$companyId];

        if (!$show_all) {
            $conditions[] = "(t.status = 'Aberto' OR t.status = 'Pendente')";
        }

        $query = "SELECT t.*,
                  u.name as requester_name, u.avatar_url as requester_avatar,
                  un.name as unit_name, a.name as asset_name, c_user.avatar_url as closer_avatar,
                  COALESCE(
                    TIMESTAMPDIFF(MINUTE, t.created_at, COALESCE(t.closed_at, NOW()))
                    - COALESCE((
                        SELECT SUM(TIMESTAMPDIFF(MINUTE, tp.paused_at, COALESCE(tp.resumed_at, NOW())))
                        FROM ticket_pauses tp WHERE tp.ticket_id COLLATE utf8mb4_unicode_ci = t.id COLLATE utf8mb4_unicode_ci
                      ), 0),
                    0
                  ) as sla_minutes,
                  (SELECT reason FROM ticket_pauses WHERE ticket_id COLLATE utf8mb4_unicode_ci = t.id COLLATE utf8mb4_unicode_ci AND resumed_at IS NULL ORDER BY paused_at DESC LIMIT 1) as pending_reason,
                  (SELECT paused_at FROM ticket_pauses WHERE ticket_id COLLATE utf8mb4_unicode_ci = t.id COLLATE utf8mb4_unicode_ci AND resumed_at IS NULL ORDER BY paused_at DESC LIMIT 1) as pending_since
                  FROM tickets t 
                  LEFT JOIN users u ON t.requester_id = u.id 
                  LEFT JOIN units un ON t.unit_id = un.id 
                  LEFT JOIN assets a ON t.asset_id = a.id
                  LEFT JOIN users c_user ON t.closed_by COLLATE utf8mb4_unicode_ci = c_user.name COLLATE utf8mb4_unicode_ci
                  WHERE " . implode(" AND ", $conditions) . " 
                  ORDER BY t.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tickets = $stmt->fetchAll();

        // Dados auxiliares para modais
        $users = $pdo->prepare("SELECT u.id, u.name, u.sector, u.role, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id = un.id WHERE u.company_id = ? ORDER BY u.name");
        $users->execute([$companyId]);
        $users = $users->fetchAll();

        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        $assets = $pdo->prepare("SELECT id, name, patrimony_id FROM assets WHERE company_id = ? ORDER BY name");
        $assets->execute([$companyId]);
        $assets = $assets->fetchAll();

        return $this->view('chamados', [
            'tickets' => $tickets,
            'users' => $users,
            'units' => $units,
            'assets' => $assets,
            'show_all' => $show_all
        ]);
    }

    public function store() {
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_ticket') {
            Auth::requirePermission('chamados.create');
            
            $priority = $_POST['priority'];
            $hours = 24;
            if ($priority === 'Crítica') $hours = 4;
            elseif ($priority === 'Alta') $hours = 8;
            elseif ($priority === 'Média') $hours = 24;
            elseif ($priority === 'Baixa') $hours = 72;
            
            $deadline = date('Y-m-d H:i:s', strtotime("+$hours hours"));

            $stmt = $pdo->prepare("INSERT INTO tickets (id, company_id, asset_id, title, description, priority, requester_id, sector, unit_id, status, created_at, sla_deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aberto', NOW(), ?)");
            $stmt->execute([
                'T' . time(), $companyId, $_POST['asset_id'] ?: null, $_POST['title'], 
                $_POST['description'], $priority, $_POST['requester_id'], 
                $_POST['sector'], $_POST['unit_id'], $deadline
            ]);
            
            Logger::audit('create_ticket', 'chamados', 'Título: ' . $_POST['title']);
            return $this->redirect('/chamados?success=1');
        }

        if ($action === 'close_ticket') {
            Auth::requirePermission('chamados.edit');
            $ticket_id = $_POST['ticket_id'];
            $resolution = $_POST['resolution'];
            $technician_name = $_POST['technician_name'] ?: $_SESSION['user_name'];

            $new_status = ($resolution === 'pendente') ? 'Pendente' : (($resolution === 'sem_solucao') ? 'Sem Solução' : 'Concluído');
            
            $stmt = $pdo->prepare("UPDATE tickets SET status = ?, closed_by = ?, closed_at = NOW(), resolved_at = NOW() WHERE id = ? AND company_id = ?");
            $stmt->execute([$new_status, $technician_name, $ticket_id, $companyId]);

            Logger::audit('close_ticket', 'chamados', 'ID: ' . $ticket_id . ' Status: ' . $new_status);
            return $this->redirect('/chamados?success=' . ($new_status == 'Pendente' ? '3' : '2'));
        }

        if ($action === 'pendenciar_ticket') {
            Auth::requirePermission('chamados.edit');
            $ticket_id = $_POST['ticket_id'];
            $reason = trim($_POST['reason'] ?? 'Aguardando peça/informação');
            
            $pdo->prepare("UPDATE tickets SET status = 'Pendente' WHERE id = ? AND company_id = ?")->execute([$ticket_id, $companyId]);
            $pdo->prepare("INSERT INTO ticket_pauses (ticket_id, paused_at, reason, paused_by) VALUES (?, NOW(), ?, ?)")
                ->execute([$ticket_id, $reason, $_SESSION['user_name']]);
            
            Logger::audit('pause_ticket', 'chamados', 'ID: ' . $ticket_id);
            return $this->redirect('/chamados?success=5');
        }

        return $this->redirect('/chamados');
    }
}
