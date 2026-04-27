<?php
require 'config.php';
$tables = ['volunteers', 'units'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM $table");
    while ($row = $stmt->fetch()) {
        if ($row['Collation']) {
            echo "{$row['Field']}: {$row['Collation']}\n";
        }
    }
}
?>
