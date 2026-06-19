<?php
require 'config.php';

$tables = [
    'users',
    'volunteers',
    'volunteer_history',
    'time_records',
    'time_incidents',
    'company_settings',
    'units',
    'sectors'
];

foreach ($tables as $table) {
    try {
        // Check if index already exists
        $stmt = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'idx_company_id'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$table` ADD INDEX `idx_company_id` (`company_id`)");
            echo "Index added to $table\n";
        } else {
            echo "Index already exists on $table\n";
        }
    } catch (Exception $e) {
        echo "Could not add index to $table: " . $e->getMessage() . "\n";
    }
}
?>
