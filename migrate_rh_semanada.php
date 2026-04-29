<?php
require_once 'config.php';

try {
    // Adicionar company_id às tabelas de RH
    $tablesRh = ['rh_employee_details', 'rh_vacations', 'rh_certificates', 'rh_notes', 'announcements'];
    foreach ($tablesRh as $table) {
        try {
            // Verificar se já existe
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'company_id'")->fetch();
            if (!$check) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN company_id INT DEFAULT 1 AFTER " . ($table === 'rh_employee_details' ? 'user_id' : 'id'));
                $pdo->exec("CREATE INDEX idx_{$table}_company ON $table(company_id)");
                echo "Tabela $table atualizada.\n";
            } else {
                echo "Tabela $table já possui company_id.\n";
            }
        } catch(Exception $e) { echo "Erro na tabela $table: " . $e->getMessage() . "\n"; }
    }

    // Tabelas Semanada
    $tablesSemanada = ['semanada_uploads', 'semanada_comments'];
    foreach ($tablesSemanada as $table) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'company_id'")->fetch();
            if (!$check) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN company_id INT DEFAULT 1 AFTER id");
                $pdo->exec("CREATE INDEX idx_{$table}_company ON $table(company_id)");
                echo "Tabela $table atualizada.\n";
            } else {
                echo "Tabela $table já possui company_id.\n";
            }
        } catch(Exception $e) { echo "Erro na tabela $table: " . $e->getMessage() . "\n"; }
    }

    echo "Migração SaaS concluída para RH e Semanada.\n";
} catch (Exception $e) {
    echo "Erro Geral: " . $e->getMessage() . "\n";
}
