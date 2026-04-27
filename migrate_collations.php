<?php
require 'config.php';
try {
    echo "Iniciando migração de collations com suporte a Foreign Keys...\n";
    
    // Desativar checks de FK
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $tables = ['volunteers', 'volunteer_hours', 'assets', 'tickets', 'loans', 'budget_requests', 'budget_quotes', 'users', 'units', 'volunteer_history', 'sectors'];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE $table CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "Tabela '$table' convertida.\n";
        } catch (Exception $e) {
            echo "Aviso ao converter '$table': " . $e->getMessage() . "\n";
        }
    }
    
    // Reativar checks de FK
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Migração concluída com sucesso!\n";
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}
?>
