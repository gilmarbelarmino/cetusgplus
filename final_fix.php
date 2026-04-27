<?php
require_once 'config.php';
$stmt = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL OR CHAR_LENGTH(status) = 0");
$stmt->execute();
echo "Rows updated: " . $stmt->rowCount() . "\n";
?>
