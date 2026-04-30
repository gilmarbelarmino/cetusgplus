<?php
require_once 'config.php';
require_once 'auth.php';

// Simular usuário logado (Projeto Arrastão)
$user = ['id' => 'U1772455737', 'company_id' => 1, 'is_super_admin' => 0];
$compId = 1;

try {
    $isSuperAdmin = ($user['is_super_admin'] ?? 0) == 1;
    $query = "SELECT u.* FROM users u WHERE 1=1";
    $params = [];
    if (!$isSuperAdmin) {
        $query .= " AND (u.company_id = ? OR u.company_id IS NULL OR u.company_id = 0)";
        $params[] = $compId;
    }
    $query .= " ORDER BY u.name ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $count = 0;
    while ($usr = $stmt->fetch()) {
        $count++;
        if ($count <= 3) echo "Found: " . $usr['name'] . PHP_EOL;
    }
    echo "TOTAL FETCHED: " . $count . PHP_EOL;
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
