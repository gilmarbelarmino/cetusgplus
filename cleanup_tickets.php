<?php
require_once 'config.php';
$stmt = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL OR TRIM(status) = ''");
$stmt->execute();
echo "Rows finalized: " . $stmt->rowCount() . "\n";
?>
