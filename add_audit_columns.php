<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE budget_requests ADD COLUMN edited_by VARCHAR(50) NULL AFTER requester_id");
    $pdo->exec("ALTER TABLE budget_requests ADD COLUMN edited_at TIMESTAMP NULL AFTER edited_by");
    echo "Colunas edited_by e edited_at adicionadas com sucesso em budget_requests!\n";
} catch (Exception $e) {
    echo "Aviso: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE volunteers ADD COLUMN last_edited_by VARCHAR(50) NULL AFTER avatar_url");
    $pdo->exec("ALTER TABLE volunteers ADD COLUMN last_edited_at TIMESTAMP NULL AFTER last_edited_by");
    echo "Colunas last_edited_by e last_edited_at adicionadas com sucesso em volunteers!\n";
} catch (Exception $e) {
    echo "Aviso: " . $e->getMessage() . "\n";
}
?>
