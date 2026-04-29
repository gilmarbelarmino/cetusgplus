<?php
require 'config.php';
$tables = ['loans', 'assets', 'tickets', 'volunteers', 'companies', 'units', 'sectors', 'roles', 'users'];
foreach ($tables as $t) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM $t LIKE 'company_id'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN company_id INT DEFAULT 1 AFTER id");
            echo "Table $t updated with company_id.\n";
        } else {
            echo "Table $t already has company_id.\n";
        }
    } catch(Exception $e) { echo "Error on $t: " . $e->getMessage() . "\n"; }
}
