<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL");
    $stmt->execute();
    $count = $stmt->rowCount();
    echo "Migration completed successfully. Updated $count tickets to 'Concluído'.";
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage();
}
