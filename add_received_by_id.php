<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE loans ADD COLUMN received_by_id VARCHAR(50) NULL AFTER received_by");
    echo "Coluna received_by_id adicionada com sucesso em loans!\n";
} catch (Exception $e) {
    echo "Aviso: " . $e->getMessage() . "\n";
}
?>
