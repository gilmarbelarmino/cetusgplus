<?php
require_once 'config.php';
$tables = ['company_settings', 'loans', 'users', 'user_menus'];
foreach ($tables as $t) {
    echo "--- TABLE: $t ---\n";
    try {
        $q = $pdo->query("DESCRIBE $t");
        print_r($q->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
