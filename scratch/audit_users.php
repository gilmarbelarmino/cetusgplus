<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=cetusg_plus', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "TOTAL USERS: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . PHP_EOL;
    
    echo "COMPANY_ID COUNTS:" . PHP_EOL;
    $res = $pdo->query("SELECT company_id, COUNT(*) as c FROM users GROUP BY company_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($res as $r) {
        echo "ID " . ($r['company_id'] ?? 'NULL') . ": " . $r['c'] . PHP_EOL;
    }
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
