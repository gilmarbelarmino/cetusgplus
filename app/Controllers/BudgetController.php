<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Uploader;

class BudgetController extends Controller {
    
    public function index() {
        Auth::requirePermission('orcamentos.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $activeTab = $_GET['tab'] ?? 'orcamentos';

        // Orçamentos
        $query = "SELECT br.*, u.name as unit_name, 
                  us.name as requester_name, us.avatar_url as requester_avatar,
                  ap.name as approver_name, ap.avatar_url as approver_avatar,
                  (SELECT COUNT(*) FROM budget_quotes WHERE budget_id = br.id) as quotes_count
                  FROM budget_requests br 
                  LEFT JOIN units u ON br.unit_id = u.id 
                  LEFT JOIN users us ON br.requester_id = us.id 
                  LEFT JOIN users ap ON br.approved_by = ap.id 
                  WHERE br.company_id = ?
                  ORDER BY br.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$companyId]);
        $budgets = $stmt->fetchAll();

        // Wishlist
        $wishlist = $pdo->prepare("SELECT w.*, s.name as sector_name, u.name as requester_name, u.avatar_url as requester_avatar,
                        ap.name as approver_name, ap.avatar_url as approver_avatar,
                        (SELECT COUNT(*) FROM wishlist_items WHERE request_id = w.id) as item_count
                        FROM wishlist_requests w
                        JOIN sectors s ON w.sector_id = s.id
                        JOIN users u ON w.requester_id = u.id
                        LEFT JOIN users ap ON w.approved_by = ap.id
                        WHERE w.company_id = ?
                        ORDER BY w.created_at DESC");
        $wishlist->execute([$companyId]);
        $wishlist = $wishlist->fetchAll();

        $units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
        $units->execute([$companyId]);
        $units = $units->fetchAll();

        $users = $pdo->prepare("SELECT id, name, sector, unit_id FROM users WHERE company_id = ? ORDER BY name");
        $users->execute([$companyId]);
        $users = $users->fetchAll();

        return $this->view('orcamentos', [
            'budgets' => $budgets,
            'wishlist' => $wishlist,
            'units' => $units,
            'users' => $users,
            'activeTab' => $activeTab
        ]);
    }

    public function store() {
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_budget') {
            Auth::requirePermission('orcamentos.create');
            $stmt = $pdo->prepare("INSERT INTO budget_requests (id, company_id, product_name, description, quantity, sector, unit_id, requester_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente', NOW())");
            $stmt->execute([
                'B' . time(), $companyId, $_POST['product_name'], $_POST['description'], 
                $_POST['quantity'], $_POST['sector'], $_POST['unit_id'], $_POST['requester_id']
            ]);
            
            Logger::audit('create_budget', 'orcamentos', 'Produto: ' . $_POST['product_name']);
            return $this->redirect('/orcamentos?success=1');
        }

        if ($action === 'approve_budget') {
            Auth::requirePermission('orcamentos.approve');
            $pdo->prepare("UPDATE budget_requests SET status = 'Aprovado', approved_by = ?, approved_at = NOW() WHERE id = ? AND company_id = ?")
                ->execute([$_SESSION['user_id'], $_POST['budget_id'], $companyId]);
            
            Logger::audit('approve_budget', 'orcamentos', 'ID: ' . $_POST['budget_id']);
            return $this->redirect('/orcamentos?success=2');
        }

        return $this->redirect('/orcamentos');
    }
}
