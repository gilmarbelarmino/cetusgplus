<?php
require_once 'config.php';

try {
    echo "Iniciando migração de colunas de SLA...\n";

    // Adicionar colunas de SLA na tabela tickets
    $queries = [
        "ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_deadline DATETIME NULL AFTER created_at",
        "ALTER TABLE tickets ADD COLUMN IF NOT EXISTS sla_status ENUM('Dentro do Prazo', 'Atrasado', 'Pausado') DEFAULT 'Dentro do Prazo' AFTER sla_deadline",
        "ALTER TABLE tickets ADD COLUMN IF NOT EXISTS resolved_at DATETIME NULL AFTER closed_at",
        "ALTER TABLE tickets MODIFY COLUMN status ENUM('Aberto', 'Em Progresso', 'Pendente', 'Concluído', 'Cancelado') DEFAULT 'Aberto'"
    ];

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
            echo "Executado: " . substr($query, 0, 50) . "...\n";
        } catch (PDOException $e) {
            echo "Aviso: " . $e->getMessage() . "\n";
        }
    }

    echo "\nMigração concluída com sucesso!\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
