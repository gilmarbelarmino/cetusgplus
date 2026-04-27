<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;

/**
 * API REST - Logs de Auditoria
 * =============================
 * Endpoint para consultar ações registradas no sistema.
 * 
 * Endpoints:
 *   GET /api/audit         → Lista últimos 50 registros
 *   GET /api/audit?user=X  → Filtra por usuário
 *   GET /api/audit?module=Y → Filtra por módulo
 */
class AuditApiController extends Controller {

    public function index() {
        $pdo = Model::getConnection();

        $where = "1=1";
        $params = [];

        // Filtros
        if (!empty($_GET['user_id'])) {
            $where .= " AND user_id = ?";
            $params[] = $_GET['user_id'];
        }
        if (!empty($_GET['module'])) {
            $where .= " AND module = ?";
            $params[] = $_GET['module'];
        }
        if (!empty($_GET['action'])) {
            $where .= " AND action = ?";
            $params[] = $_GET['action'];
        }
        if (!empty($_GET['from'])) {
            $where .= " AND created_at >= ?";
            $params[] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where .= " AND created_at <= ?";
            $params[] = $_GET['to'] . ' 23:59:59';
        }

        $limit = min((int)($_GET['limit'] ?? 50), 200);

        $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Total para paginação
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");
        $stmtCount->execute($params);
        $total = $stmtCount->fetchColumn();

        return $this->json([
            'success' => true,
            'total' => (int)$total,
            'limit' => $limit,
            'data' => $logs
        ]);
    }
}
