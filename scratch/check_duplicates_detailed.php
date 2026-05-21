<?php
require_once 'config.php';
$stmt = $pdo->prepare('SELECT id, name, responsible_name, responsible_id, company_id, created_at FROM assets WHERE name LIKE "%HDMI 2%" OR name LIKE "%teste%" ORDER BY created_at DESC');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
