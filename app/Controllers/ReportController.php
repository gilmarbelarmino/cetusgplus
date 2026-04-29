<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;

class ReportController extends Controller {
    
    public function index() {
        Auth::requirePermission('relatorios.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        // Visão Geral
        $totalAssets = $this->queryValue($pdo, "SELECT COUNT(*) FROM assets WHERE company_id = ?", [$companyId]);
        $totalTickets = $this->queryValue($pdo, "SELECT COUNT(*) FROM tickets WHERE company_id = ?", [$companyId]);
        $totalUsers = $this->queryValue($pdo, "SELECT COUNT(*) FROM users WHERE company_id = ?", [$companyId]);
        $totalLoans = $this->queryValue($pdo, "SELECT COUNT(*) FROM loans WHERE company_id = ?", [$companyId]);
        $totalVolunteers = $this->queryValue($pdo, "SELECT COUNT(*) FROM volunteers WHERE company_id = ?", [$companyId]);
        $totalHours = $this->queryValue($pdo, "SELECT SUM(total_hours) FROM volunteers WHERE company_id = ?", [$companyId]) ?: 0;

        // SLA Data (Simplified for brevity in the summary)
        $slaAvgTotal = $this->queryValue($pdo, "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) / 60, 1) FROM tickets WHERE company_id = ? AND closed_at IS NOT NULL", [$companyId]);

        return $this->view('relatorios', [
            'totalAssets' => $totalAssets,
            'totalTickets' => $totalTickets,
            'totalUsers' => $totalUsers,
            'totalLoans' => $totalLoans,
            'totalVolunteers' => $totalVolunteers,
            'totalHours' => $totalHours,
            'slaAvgTotal' => $slaAvgTotal
        ]);
    }

    private function queryValue($pdo, $sql, $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
