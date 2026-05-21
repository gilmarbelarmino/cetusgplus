<?php
/**
 * PRODUCTION DATABASE PATCH - Run this once on Hostinger
 * This script fixes the assets table schema and cleans up data.
 */
require_once 'config.php';

echo "<pre>";
echo "Starting Production Database Patch...\n";

try {
    // 1. Fix the column type
    $pdo->exec("ALTER TABLE assets MODIFY responsible_id VARCHAR(50) NULL");
    echo "[SUCCESS] Column assets.responsible_id modified to VARCHAR(50).\n";
    
    // 2. Cleanup invalid zero IDs
    $stmt = $pdo->prepare("UPDATE assets SET responsible_id = NULL WHERE responsible_id = '0'");
    $stmt->execute();
    echo "[SUCCESS] Cleaned up " . $stmt->rowCount() . " rows with responsible_id = '0'.\n";
    
    // 3. Optional: Verify the structure
    $stmt = $pdo->query("DESCRIBE assets");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nNew Assets Structure:\n";
    foreach ($structure as $col) {
        echo " - {$col['Field']}: {$col['Type']}\n";
    }
    
    echo "\nPatch completed successfully. Please DELETE this file from the server now for security.";

} catch (Exception $e) {
    echo "[ERROR] Patch failed: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
