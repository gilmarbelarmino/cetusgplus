<?php
require 'config.php';
$tables = ['assets', 'loans', 'budget_requests', 'volunteers', 'users'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
?>
