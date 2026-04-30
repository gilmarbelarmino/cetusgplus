<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$compId = getCurrentUserCompanyId();
$action = $_GET['action'] ?? '';

if ($action === 'get_kpis') {
    $stats = [];

    // 1. Patrimônio (Sincronizado com api_patrimonio.php)
    $stats['assets_count'] = $this_query($pdo, "SELECT COUNT(*) FROM assets WHERE company_id = ?", [$compId]);
    $stats['assets_value'] = (float)($this_query($pdo, "SELECT SUM(estimated_value) FROM assets WHERE company_id = ?", [$compId]));
    $stats['assets_active_pct'] = $stats['assets_count'] > 0 ? round(($this_query($pdo, "SELECT COUNT(*) FROM assets WHERE status = 'Ativo' AND company_id = ?", [$compId]) / $stats['assets_count']) * 100, 1) : 0;
    
    // 2. Chamados (Sincronizado com api_chamados.php)
    $stats['tickets_total'] = $this_query($pdo, "SELECT COUNT(*) FROM tickets WHERE company_id = ?", [$compId]);
    $stats['tickets_open'] = $this_query($pdo, "SELECT COUNT(*) FROM tickets WHERE (status = 'Aberto' OR status = 'Pendente') AND company_id = ?", [$compId]);
    $stats['tickets_resolve_pct'] = $stats['tickets_total'] > 0 ? round((($stats['tickets_total'] - $stats['tickets_open']) / $stats['tickets_total']) * 100, 1) : 0;
    
    // 3. Empréstimos (Sincronizado com api_emprestimos.php)
    $stats['loans_active'] = $this_query($pdo, "SELECT COUNT(*) FROM loans WHERE status = 'Emprestado' AND company_id = ?", [$compId]);
    $stats['loans_total'] = $this_query($pdo, "SELECT COUNT(*) FROM loans WHERE company_id = ?", [$compId]);
    $stats['loans_return_pct'] = $stats['loans_total'] > 0 ? round((($stats['loans_total'] - $stats['loans_active']) / $stats['loans_total']) * 100, 1) : 0;
    
    // 4. Voluntariado (Sincronizado com api_voluntariado.php)
    $stats['volunteers_count'] = $this_query($pdo, "SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo' AND company_id = ?", [$compId]);
    $stats['volunteers_hours'] = (float)($this_query($pdo, "SELECT SUM(total_hours) FROM volunteers WHERE company_id = ?", [$compId]));
    $stats['volunteers_impact'] = (float)($this_query($pdo, "SELECT SUM(total_hours * hourly_rate) FROM volunteers WHERE company_id = ?", [$compId]));
    
    $total_vols = $this_query($pdo, "SELECT COUNT(*) FROM volunteers WHERE company_id = ?", [$compId]) ?: 1;
    $stats['volunteers_active_pct'] = round(($stats['volunteers_count'] / $total_vols) * 100, 1);

    // 5. Orçamentos
    try {
        $stats['budgets_pending'] = $this_query($pdo, "SELECT COUNT(*) FROM budget_requests WHERE status = 'Pendente' AND company_id = ?", [$compId]);
        $total_budgets = $this_query($pdo, "SELECT COUNT(*) FROM budget_requests WHERE company_id = ?", [$compId]) ?: 1;
        $stats['budgets_approve_pct'] = round((($total_budgets - $stats['budgets_pending']) / $total_budgets) * 100, 1);
    } catch(Exception $e) { $stats['budgets_pending'] = 0; $stats['budgets_approve_pct'] = 0; }

    echo json_encode($stats);
    exit;
}

if ($action === 'get_charts') {
    $charts = [];

    // Pat: Status
    $stmt1 = $pdo->prepare("SELECT status as name, COUNT(*) as value FROM assets WHERE company_id = ? GROUP BY status");
    $stmt1->execute([$compId]);
    $charts['patrimonio_status'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Tickets: Status
    $stmt2 = $pdo->prepare("SELECT status as name, COUNT(*) as value FROM tickets WHERE company_id = ? GROUP BY status");
    $stmt2->execute([$compId]);
    $charts['tickets_status'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Loans: By Sector
    $stmt3 = $pdo->prepare("SELECT sector as name, COUNT(*) as value FROM loans WHERE company_id = ? GROUP BY sector ORDER BY value DESC LIMIT 6");
    $stmt3->execute([$compId]);
    $charts['loans_sector'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    // Budgets: Status
    try {
        $stmt4 = $pdo->prepare("SELECT status as name, COUNT(*) as value FROM budget_requests WHERE company_id = ? GROUP BY status");
        $stmt4->execute([$compId]);
        $charts['budgets_status'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $charts['budgets_status'] = []; }

    echo json_encode($charts);
    exit;
}

function this_query($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}
?>
