<?php
require 'config.php';
$stmt = $pdo->query('SHOW COLUMNS FROM users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
