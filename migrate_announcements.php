<?php
require_once 'config.php';

try {
    // Tabela de Recados
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100)
    )");

    // Tabela de Visualizações (quem já viu qual recado)
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (announcement_id),
        INDEX (user_id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
    )");

    echo "Tabelas de recados criadas com sucesso!";
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage();
}
