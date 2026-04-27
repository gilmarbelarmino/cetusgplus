<?php
require_once 'config.php';

try {
    // Adicionar colunas para o comunicado global
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN announcement_image_url TEXT AFTER login_announcement");
    echo "Coluna announcement_image_url adicionada com sucesso!\n";
} catch(Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Coluna já existe.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
    }
}
?>
