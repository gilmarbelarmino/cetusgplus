<?php
require_once 'config.php';
$stmt = $pdo->query('SELECT id, name, COUNT(*) as count FROM users GROUP BY id HAVING count > 1');
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Duplicate IDs: " . json_encode($dupes) . "\n";

$stmt = $pdo->query('SELECT id, name FROM users WHERE id = 0 OR id IS NULL OR id = ""');
$zeros = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Zeros/Nulls: " . json_encode($zeros) . "\n";
?>
