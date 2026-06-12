<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE assets");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "ASSETS TABLE:\n";
print_r($columns);

$stmt = $pdo->query("SELECT * FROM assets LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nSAMPLE ROW:\n";
print_r($row);
?>
