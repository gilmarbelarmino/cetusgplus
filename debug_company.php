<?php
require_once 'config.php';
$stmt = $pdo->query("DESCRIBE company_settings");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols);
?>
