<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE volunteer_history ADD COLUMN edited_by VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE volunteer_history ADD COLUMN edited_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Exception $e) { echo "Aviso: " . $e->getMessage(); }
?>
