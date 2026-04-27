<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db_config.php';

$action = $_GET['action'] ?? '';

function getPercentage($part, $total) {
    return $total > 0 ? round(($part / $total) * 100, 1) : 0;
}

if ($action === 'overview') {
    $data = [];
    
    // Assets
    $total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() ?: 0;
    $assets_raw = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $data['assets'] = array_map(function($row) use ($total_assets) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_assets) . '%)'];
    }, $assets_raw);

    // Tickets
    $total_tickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0;
    $tickets_raw = $pdo->query("SELECT status, COUNT(*) as count FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $data['tickets'] = array_map(function($row) use ($total_tickets) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_tickets) . '%)'];
    }, $tickets_raw);

    // Loans by Sector
    $data['loans'] = $pdo->query("SELECT sector as name, COUNT(*) as value FROM loans GROUP BY sector ORDER BY value DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Volunteers
    $total_vols = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0;
    $vols_raw = $pdo->query("SELECT status, COUNT(*) as count FROM volunteers GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $data['volunteers'] = array_map(function($row) use ($total_vols) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_vols) . '%)'];
    }, $vols_raw);

    echo json_encode($data);
    exit;
}

if ($action === 'assets_detail') {
    $data = [];
    $total = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() ?: 0;
    
    $data['by_status'] = $pdo->query("SELECT status as name, COUNT(*) as value FROM assets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $data['by_sector'] = $pdo->query("SELECT sector as name, COUNT(*) as value FROM assets GROUP BY sector ORDER BY value DESC")->fetchAll(PDO::FETCH_ASSOC);
    $data['value_by_sector'] = $pdo->query("SELECT sector as name, SUM(estimated_value) as value FROM assets GROUP BY sector ORDER BY value DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Percentages for UI
    foreach($data['by_status'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }
    
    echo json_encode($data);
    exit;
}

if ($action === 'tickets_detail') {
    $data = [];
    $total = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0;
    
    $data['by_status'] = $pdo->query("SELECT status as name, COUNT(*) as value FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    $data['by_sector'] = $pdo->query("SELECT sector as name, COUNT(*) as value FROM tickets GROUP BY sector ORDER BY value DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($data['by_status'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }

    // Trend (Last 6 months)
    $sql = "SELECT DATE_FORMAT(created_at, '%b') as name, COUNT(*) as value 
            FROM tickets 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
            GROUP BY name ORDER BY MIN(created_at)";
    $data['trend'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

if ($action === 'loans_detail') {
    $data = [];
    $data['by_category'] = $pdo->query("SELECT a.category as name, COUNT(l.id) as value FROM loans l JOIN assets a ON l.asset_id = a.id GROUP BY a.category")->fetchAll(PDO::FETCH_ASSOC);
    $data['ranking'] = $pdo->query("SELECT borrower_name as name, COUNT(*) as value FROM loans GROUP BY borrower_name ORDER BY value DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

if ($action === 'volunteer_detail') {
    $data = [];
    $total = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0;
    
    $data['by_sex'] = $pdo->query("SELECT gender as name, COUNT(*) as value FROM volunteers GROUP BY gender")->fetchAll(PDO::FETCH_ASSOC);
    foreach($data['by_sex'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }

    $data['by_type'] = $pdo->query("SELECT location as name, COUNT(*) as value FROM volunteers GROUP BY location")->fetchAll(PDO::FETCH_ASSOC);
    
    // Finance impact monthly
    $months = ['hours_jan','hours_feb','hours_mar','hours_apr','hours_may','hours_jun','hours_jul','hours_aug','hours_sep','hours_oct','hours_nov','hours_dec'];
    $impact = [];
    foreach(['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $idx => $mName) {
        $field = $months[$idx];
        $sum = $pdo->query("SELECT SUM($field * hourly_rate) FROM volunteers")->fetchColumn() ?: 0;
        $impact[] = ['name' => $mName, 'value' => (float)$sum];
    }
    $data['finance_impact'] = $impact;
    echo json_encode($data);
    exit;
}

if ($action === 'rh_detail') {
    $data = [];
    try {
        $data['contract_types'] = $pdo->query("SELECT contract_type as name, COUNT(*) as value FROM rh_employee_details GROUP BY contract_type")->fetchAll(PDO::FETCH_ASSOC);
        $data['genders'] = $pdo->query("SELECT gender as name, COUNT(*) as value FROM rh_employee_details GROUP BY gender")->fetchAll(PDO::FETCH_ASSOC);
        $data['avg_salary_sector'] = $pdo->query("SELECT u.sector as name, AVG(rh.salary) as value FROM users u JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id GROUP BY u.sector")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $data['error'] = 'RH tables not fully mapped'; }
    echo json_encode($data);
    exit;
}
?>
