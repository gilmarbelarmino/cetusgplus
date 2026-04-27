<?php
require_once 'config.php';
$pdo->exec("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL");
echo "Done fixing " . $pdo->lastInsertId() . " (use rowCount instead)\n";
$stmt = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL");
$stmt->execute();
echo "Real rows updated: " . $stmt->rowCount() . "\n";
?>
