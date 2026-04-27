<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE loans ADD COLUMN borrower_id VARCHAR(50) NULL AFTER asset_name");
    echo "Coluna borrower_id adicionada com sucesso em loans!\n";
} catch (Exception $e) {
    echo "Aviso: " . $e->getMessage() . "\n";
}
?>
