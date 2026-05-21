<?php
$pdo = new PDO('mysql:host=localhost;dbname=cetusg_plus', 'root', '');
$stmt = $pdo->query('DESCRIBE assets');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT);
?>
