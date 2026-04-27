<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE volunteers ADD COLUMN avatar_url VARCHAR(255) NULL AFTER name");
    echo "Coluna avatar_url adicionada com sucesso!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "A coluna avatar_url já existe.\n";
    } else {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
?>
