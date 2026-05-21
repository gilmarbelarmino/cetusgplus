<?php
$pdo = new PDO('mysql:host=localhost;dbname=cetusg_plus', 'root', '');
$stmt = $pdo->prepare('SELECT id, name, created_at, company_id, responsible_name, responsible_id FROM assets WHERE name LIKE "%hdmi 2%"');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
