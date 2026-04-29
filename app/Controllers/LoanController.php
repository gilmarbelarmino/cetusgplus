<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class LoanController extends Controller {
    
    public function index() {
        Auth::requirePermission('emprestimos.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $view = $_GET['view'] ?? 'ativos';
        $query = "
            SELECT l.*, u.name as unit_name, 
                   bor.name as borrower_name, bor.avatar_url as borrower_avatar
            FROM loans l 
            LEFT JOIN units u ON l.unit_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci
            LEFT JOIN users bor ON l.borrower_id COLLATE utf8mb4_unicode_ci = bor.id COLLATE utf8mb4_unicode_ci
            WHERE l.company_id = ?
        ";
        
        if ($view === 'ativos') {
            $query .= " AND l.status = 'Ativo'";
        } elseif ($view === 'fechados') {
            $query .= " AND l.status = 'Devolvido'";
        }
        
        $query .= " ORDER BY l.loan_date DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$companyId]);
        $loans = $stmt->fetchAll();

        // Dados para formulário
        $assets = $pdo->prepare("SELECT * FROM assets WHERE company_id = ? AND status = 'Ativo'");
        $assets->execute([$companyId]);
        $assets = $assets->fetchAll();

        $users = $pdo->prepare("SELECT * FROM users WHERE company_id = ? ORDER BY name");
        $users->execute([$companyId]);
        $users = $users->fetchAll();

        return $this->view('emprestimos', [
            'loans' => $loans,
            'assets' => $assets,
            'users' => $users,
            'view' => $view
        ]);
    }

    public function store() {
        Auth::requirePermission('emprestimos.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_loan') {
            $pdo->prepare("INSERT INTO loans (id, company_id, asset_id, borrower_id, asset_name, loan_date, expected_return_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Ativo')")
                ->execute([
                    'L'.time(), $companyId, $_POST['asset_id'], $_POST['borrower_id'], 
                    $_POST['asset_name'] ?? 'Equipamento', $_POST['loan_date'], $_POST['expected_return_date']
                ]);
            
            // Marcar asset como emprestado
            $pdo->prepare("UPDATE assets SET status = 'Emprestado' WHERE id = ? AND company_id = ?")
                ->execute([$_POST['asset_id'], $companyId]);

            Logger::audit('add_loan', 'assets', 'Empréstimo ID: ' . $_POST['asset_id']);
            return $this->redirect('/emprestimos?success=1');
        }

        return $this->redirect('/emprestimos');
    }
}
