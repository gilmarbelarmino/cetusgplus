<?php
require_once 'config.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Acesso negado. Sessão inválida.']);
    exit();
}

$stmt = $pdo->prepare("SELECT login_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$login_name = $stmt->fetchColumn();

if ($login_name !== 'superadmin') {
    echo json_encode(['error' => 'Acesso negado. Apenas superadmin.']);
    exit();
}

try {
    $report = [
        'users' => [],
        'assets' => [],
        'tickets' => [],
        'volunteers' => [],
        'volunteers_gender' => [],
        'volunteers_sector' => []
    ];

    // 1. USUÁRIOS
    $stmtUsers = $pdo->query("
        SELECT t.name as company_name, COUNT(u.id) as total_users
        FROM tenants t
        LEFT JOIN users u ON u.company_id = t.id AND u.status = 'Ativo'
        GROUP BY t.id
        ORDER BY total_users DESC
    ");
    $usersData = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    $totalAllUsers = array_sum(array_column($usersData, 'total_users'));
    
    foreach ($usersData as &$row) {
        $row['total_users'] = (int)$row['total_users'];
        $row['percentage'] = $totalAllUsers > 0 ? round(($row['total_users'] / $totalAllUsers) * 100, 1) : 0;
    }
    $report['users'] = [
        'data' => $usersData,
        'grand_total' => $totalAllUsers
    ];

    // 2. PATRIMÔNIO (ASSETS)
    $stmtAssets = $pdo->query("
        SELECT t.name as company_name, 
               COUNT(a.id) as total_assets,
               SUM(COALESCE(a.estimated_value, 0)) as total_value
        FROM tenants t
        LEFT JOIN assets a ON a.company_id = t.id AND a.status != 'Inativo'
        GROUP BY t.id
        ORDER BY total_assets DESC
    ");
    $assetsData = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);
    $totalAllAssets = array_sum(array_column($assetsData, 'total_assets'));
    $totalAllValue = array_sum(array_column($assetsData, 'total_value'));

    foreach ($assetsData as &$row) {
        $row['total_assets'] = (int)$row['total_assets'];
        $row['total_value'] = (float)$row['total_value'];
        $row['qty_percentage'] = $totalAllAssets > 0 ? round(($row['total_assets'] / $totalAllAssets) * 100, 1) : 0;
        $row['val_percentage'] = $totalAllValue > 0 ? round(($row['total_value'] / $totalAllValue) * 100, 1) : 0;
    }
    $report['assets'] = [
        'data' => $assetsData,
        'grand_total_qty' => $totalAllAssets,
        'grand_total_value' => $totalAllValue
    ];

    // 3. CHAMADOS (TICKETS)
    $stmtTickets = $pdo->query("
        SELECT t.name as company_name, COUNT(tk.id) as total_tickets
        FROM tenants t
        LEFT JOIN tickets tk ON tk.company_id = t.id
        GROUP BY t.id
        ORDER BY total_tickets DESC
    ");
    $ticketsData = $stmtTickets->fetchAll(PDO::FETCH_ASSOC);
    $totalAllTickets = array_sum(array_column($ticketsData, 'total_tickets'));

    foreach ($ticketsData as &$row) {
        $row['total_tickets'] = (int)$row['total_tickets'];
        $row['percentage'] = $totalAllTickets > 0 ? round(($row['total_tickets'] / $totalAllTickets) * 100, 1) : 0;
    }
    $report['tickets'] = [
        'data' => $ticketsData,
        'grand_total' => $totalAllTickets
    ];

    // 4. VOLUNTARIADO
    $stmtVol = $pdo->query("
        SELECT t.name as company_name, 
               COUNT(v.id) as total_volunteers,
               SUM(COALESCE(v.total_hours, 0)) as total_hours,
               SUM(COALESCE(v.total_hours, 0) * COALESCE(v.hourly_rate, 0)) as returned_value
        FROM tenants t
        LEFT JOIN volunteers v ON v.company_id = t.id AND v.status = 'Ativo'
        GROUP BY t.id
        ORDER BY total_volunteers DESC
    ");
    $volData = $stmtVol->fetchAll(PDO::FETCH_ASSOC);
    
    $grandTotalVolunteers = array_sum(array_column($volData, 'total_volunteers'));
    $grandTotalHours = array_sum(array_column($volData, 'total_hours'));
    $grandTotalReturned = array_sum(array_column($volData, 'returned_value'));

    foreach ($volData as &$row) {
        $row['total_volunteers'] = (int)$row['total_volunteers'];
        $row['total_hours'] = (int)$row['total_hours'];
        $row['returned_value'] = (float)$row['returned_value'];
        $row['vol_percentage'] = $grandTotalVolunteers > 0 ? round(($row['total_volunteers'] / $grandTotalVolunteers) * 100, 1) : 0;
        $row['hours_percentage'] = $grandTotalHours > 0 ? round(($row['total_hours'] / $grandTotalHours) * 100, 1) : 0;
    }
    $report['volunteers'] = [
        'data' => $volData,
        'grand_total_volunteers' => $grandTotalVolunteers,
        'grand_total_hours' => $grandTotalHours,
        'grand_total_returned' => $grandTotalReturned
    ];

    // 4.1 Voluntariado por Sexo
    $stmtGender = $pdo->query("
        SELECT COALESCE(gender, 'Não Informado') as gender, COUNT(id) as total
        FROM volunteers WHERE status = 'Ativo' GROUP BY gender
    ");
    $report['volunteers_gender'] = $stmtGender->fetchAll(PDO::FETCH_ASSOC);

    // 4.2 Voluntariado por Setor
    $stmtSector = $pdo->query("
        SELECT COALESCE(volunteering_sector, 'Sem Setor') as sector, COUNT(id) as total
        FROM volunteers WHERE status = 'Ativo' GROUP BY volunteering_sector ORDER BY total DESC LIMIT 10
    ");
    $report['volunteers_sector'] = $stmtSector->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($report);

} catch (Exception $e) {
    echo json_encode(['error' => 'Falha ao processar relatórios: ' . $e->getMessage()]);
}
