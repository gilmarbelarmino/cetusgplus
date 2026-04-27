<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;

/**
 * API REST - Dashboard
 * ====================
 * Fornece dados em JSON para integrações externas,
 * aplicativos mobile e painéis de BI.
 * 
 * Endpoints:
 *   GET /api/dashboard       → Estatísticas gerais
 *   GET /api/dashboard/stats → KPIs do sistema
 */
class DashboardApiController extends Controller {

    public function index() {
        $pdo = Model::getConnection();

        // Contadores gerais
        $stats = [
            'total_usuarios' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_chamados' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0,
            'chamados_abertos' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'")->fetchColumn() ?: 0,
            'total_cameras' => $pdo->query("SELECT COUNT(*) FROM tech_cameras")->fetchColumn() ?: 0,
            'total_emprestimos' => $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Emprestado'")->fetchColumn() ?: 0,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function stats() {
        $pdo = Model::getConnection();

        // KPIs mensais
        $month = date('Y-m');
        
        $kpis = [
            'chamados_mes' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn() ?: 0,
            'notas_criadas' => $pdo->query("SELECT COUNT(*) FROM tech_notes WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn() ?: 0,
            'acoes_auditadas' => $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn() ?: 0,
        ];

        return $this->json([
            'success' => true,
            'period' => $month,
            'kpis' => $kpis
        ]);
    }
}
