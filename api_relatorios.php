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

function getPercentage($part, $total) {
    return $total > 0 ? round(($part / $total) * 100, 1) : 0;
}

if ($action === 'overview') {
    $data = [];
    
    // Assets
    $total_assets = this_query($pdo, "SELECT COUNT(*) FROM assets WHERE company_id = ?", [$compId]);
    $stmt1 = $pdo->prepare("SELECT status, COUNT(*) as count FROM assets WHERE company_id = ? GROUP BY status");
    $stmt1->execute([$compId]);
    $assets_raw = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    $data['assets'] = array_map(function($row) use ($total_assets) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_assets) . '%)'];
    }, $assets_raw);

    // Tickets
    $total_tickets = this_query($pdo, "SELECT COUNT(*) FROM tickets WHERE company_id = ?", [$compId]);
    $stmt2 = $pdo->prepare("SELECT status, COUNT(*) as count FROM tickets WHERE company_id = ? GROUP BY status");
    $stmt2->execute([$compId]);
    $tickets_raw = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $data['tickets'] = array_map(function($row) use ($total_tickets) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_tickets) . '%)'];
    }, $tickets_raw);

    // Loans by Sector
    $stmt3 = $pdo->prepare("SELECT sector as name, COUNT(*) as value FROM loans WHERE company_id = ? GROUP BY sector ORDER BY value DESC");
    $stmt3->execute([$compId]);
    $data['loans'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    // Volunteers
    $total_vols = this_query($pdo, "SELECT COUNT(*) FROM volunteers WHERE company_id = ?", [$compId]);
    $stmt4 = $pdo->prepare("SELECT status, COUNT(*) as count FROM volunteers WHERE company_id = ? GROUP BY status");
    $stmt4->execute([$compId]);
    $vols_raw = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    $data['volunteers'] = array_map(function($row) use ($total_vols) {
        return ['name' => $row['status'], 'value' => (int)$row['count'], 'display' => $row['status'] . ' (' . getPercentage($row['count'], $total_vols) . '%)'];
    }, $vols_raw);

    echo json_encode($data);
    exit;
}

if ($action === 'assets_detail') {
    $data = [];
    $total = this_query($pdo, "SELECT COUNT(*) FROM assets WHERE company_id = ?", [$compId]);
    
    $stmt1 = $pdo->prepare("SELECT status as name, COUNT(*) as value FROM assets WHERE company_id = ? GROUP BY status");
    $stmt1->execute([$compId]);
    $data['by_status'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT sector as name, COUNT(*) as value FROM assets WHERE company_id = ? GROUP BY sector ORDER BY value DESC");
    $stmt2->execute([$compId]);
    $data['by_sector'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $stmt3 = $pdo->prepare("SELECT sector as name, SUM(estimated_value) as value FROM assets WHERE company_id = ? GROUP BY sector ORDER BY value DESC");
    $stmt3->execute([$compId]);
    $data['value_by_sector'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    // Percentages for UI
    foreach($data['by_status'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }
    
    echo json_encode($data);
    exit;
}

if ($action === 'tickets_detail') {
    $data = [];
    $total = this_query($pdo, "SELECT COUNT(*) FROM tickets WHERE company_id = ?", [$compId]);
    
    $stmt1 = $pdo->prepare("SELECT status as name, COUNT(*) as value FROM tickets WHERE company_id = ? GROUP BY status");
    $stmt1->execute([$compId]);
    $data['by_status'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT sector as name, COUNT(*) as value FROM tickets WHERE company_id = ? GROUP BY sector ORDER BY value DESC");
    $stmt2->execute([$compId]);
    $data['by_sector'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($data['by_status'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }

    // Trend (Last 6 months)
    $sql = "SELECT DATE_FORMAT(created_at, '%b') as name, COUNT(*) as value 
            FROM tickets 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND company_id = ?
            GROUP BY name ORDER BY MIN(created_at)";
    $stmt3 = $pdo->prepare($sql);
    $stmt3->execute([$compId]);
    $data['trend'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

if ($action === 'loans_detail') {
    $data = [];
    $stmt1 = $pdo->prepare("SELECT a.category as name, COUNT(l.id) as value FROM loans l JOIN assets a ON l.asset_id = a.id WHERE l.company_id = ? GROUP BY a.category");
    $stmt1->execute([$compId]);
    $data['by_category'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->prepare("SELECT borrower_name as name, COUNT(*) as value FROM loans WHERE company_id = ? GROUP BY borrower_name ORDER BY value DESC LIMIT 10");
    $stmt2->execute([$compId]);
    $data['ranking'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
    exit;
}

if ($action === 'volunteer_detail') {
    $data = [];
    $total = this_query($pdo, "SELECT COUNT(*) FROM volunteers WHERE company_id = ?", [$compId]);
    
    $stmt1 = $pdo->prepare("SELECT gender as name, COUNT(*) as value FROM volunteers WHERE company_id = ? GROUP BY gender");
    $stmt1->execute([$compId]);
    $data['by_sex'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);
    foreach($data['by_sex'] as &$item) { $item['pct'] = getPercentage($item['value'], $total); }

    $stmt2 = $pdo->prepare("SELECT location as name, COUNT(*) as value FROM volunteers WHERE company_id = ? GROUP BY location");
    $stmt2->execute([$compId]);
    $data['by_type'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Finance impact monthly
    $months = ['hours_jan','hours_feb','hours_mar','hours_apr','hours_may','hours_jun','hours_jul','hours_aug','hours_sep','hours_oct','hours_nov','hours_dec'];
    $impact = [];
    foreach(['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $idx => $mName) {
        $field = $months[$idx];
        $sum = this_query($pdo, "SELECT SUM($field * hourly_rate) FROM volunteers WHERE company_id = ?", [$compId]);
        $impact[] = ['name' => $mName, 'value' => (float)$sum];
    }
    $data['finance_impact'] = $impact;
    echo json_encode($data);
    exit;
}

if ($action === 'rh_detail') {
    $data = [];
    try {
        $stmt1 = $pdo->prepare("SELECT contract_type as name, COUNT(*) as value FROM rh_employee_details WHERE company_id = ? GROUP BY contract_type");
        $stmt1->execute([$compId]);
        $data['contract_types'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT gender as name, COUNT(*) as value FROM rh_employee_details WHERE company_id = ? GROUP BY gender");
        $stmt2->execute([$compId]);
        $data['genders'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $stmt3 = $pdo->prepare("SELECT u.sector as name, AVG(rh.salary) as value FROM users u JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id WHERE u.company_id = ? GROUP BY u.sector");
        $stmt3->execute([$compId]);
        $data['avg_salary_sector'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $data['error'] = 'RH tables not fully mapped'; }
    echo json_encode($data);
    exit;
}

function this_query($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}
?>
