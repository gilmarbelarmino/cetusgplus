<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS certificate_global_text TEXT AFTER certificate_signature_url");
    echo "Coluna certificate_global_text adicionada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro ao adicionar coluna: " . $e->getMessage() . "\n";
}
