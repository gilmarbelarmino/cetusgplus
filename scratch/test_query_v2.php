<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cetusg_plus');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $compId = 1;
    $isSuperAdmin = false;

    $query = "SELECT u.* FROM users u WHERE 1=1";
    $params = [];
    if (!$isSuperAdmin) {
        $query .= " AND (u.company_id = ? OR u.company_id IS NULL OR u.company_id = 0)";
        $params[] = $compId;
    }
    $query .= " ORDER BY u.name ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $all_users = $stmt->fetchAll();
    echo "TOTAL FETCHED: " . count($all_users) . PHP_EOL;
    foreach(array_slice($all_users, 0, 3) as $usr) {
        echo "- " . $usr['name'] . " (Company: " . ($usr['company_id'] ?? 'NULL') . ")" . PHP_EOL;
    }
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
