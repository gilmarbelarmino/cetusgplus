<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db_config.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_kpis') {
    $stats = [];

    // 1. Patrimônio (Sincronizado com api_patrimonio.php)
    $stats['assets_count'] = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() ?: 0;
    $stats['assets_value'] = (float)($pdo->query("SELECT SUM(estimated_value) FROM assets")->fetchColumn() ?: 0);
    $stats['assets_active_pct'] = $stats['assets_count'] > 0 ? round(($pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'Ativo'")->fetchColumn() / $stats['assets_count']) * 100, 1) : 0;
    
    // 2. Chamados (Sincronizado com api_chamados.php)
    $stats['tickets_total'] = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0;
    $stats['tickets_open'] = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto' OR status = 'Pendente'")->fetchColumn() ?: 0;
    $stats['tickets_resolve_pct'] = $stats['tickets_total'] > 0 ? round((($stats['tickets_total'] - $stats['tickets_open']) / $stats['tickets_total']) * 100, 1) : 0;
    
    // 3. Empréstimos (Sincronizado com api_emprestimos.php)
    $stats['loans_active'] = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Emprestado'")->fetchColumn() ?: 0;
    $stats['loans_total'] = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn() ?: 0;
    $stats['loans_return_pct'] = $stats['loans_total'] > 0 ? round((($stats['loans_total'] - $stats['loans_active']) / $stats['loans_total']) * 100, 1) : 0;
    
    // 4. Voluntariado (Sincronizado com api_voluntariado.php)
    $stats['volunteers_count'] = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'")->fetchColumn() ?: 0;
    $stats['volunteers_hours'] = (float)($pdo->query("SELECT SUM(total_hours) FROM volunteers")->fetchColumn() ?: 0);
    $stats['volunteers_impact'] = (float)($pdo->query("SELECT SUM(total_hours * hourly_rate) FROM volunteers")->fetchColumn() ?: 0);
    
    $total_vols = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 1;
    $stats['volunteers_active_pct'] = round(($stats['volunteers_count'] / $total_vols) * 100, 1);

    // 5. Orçamentos
    try {
        $stats['budgets_pending'] = $pdo->query("SELECT COUNT(*) FROM budget_requests WHERE status = 'Pendente'")->fetchColumn() ?: 0;
        $total_budgets = $pdo->query("SELECT COUNT(*) FROM budget_requests")->fetchColumn() ?: 1;
        $stats['budgets_approve_pct'] = round((($total_budgets - $stats['budgets_pending']) / $total_budgets) * 100, 1);
    } catch(Exception $e) { $stats['budgets_pending'] = 0; $stats['budgets_approve_pct'] = 0; }

    echo json_encode($stats);
    exit;
}

if ($action === 'get_charts') {
    $charts = [];

    // Pat: Status
    $charts['patrimonio_status'] = $pdo->query("SELECT status as name, COUNT(*) as value FROM assets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

    // Tickets: Status
    $charts['tickets_status'] = $pdo->query("SELECT status as name, COUNT(*) as value FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);

    // Loans: By Sector
    $charts['loans_sector'] = $pdo->query("SELECT sector as name, COUNT(*) as value FROM loans GROUP BY sector ORDER BY value DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    // Budgets: Status
    try {
        $charts['budgets_status'] = $pdo->query("SELECT status as name, COUNT(*) as value FROM budget_requests GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $charts['budgets_status'] = []; }

    echo json_encode($charts);
    exit;
}
?>
