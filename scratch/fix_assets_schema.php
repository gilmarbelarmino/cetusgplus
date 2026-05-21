<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE assets MODIFY responsible_id VARCHAR(50) NULL");
    echo "Column responsible_id modified to VARCHAR(50)\n";
    
    $stmt = $pdo->prepare("UPDATE assets SET responsible_id = NULL WHERE responsible_id = '0'");
    $stmt->execute();
    echo "Cleaned up " . $stmt->rowCount() . " rows with responsible_id = '0'\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
