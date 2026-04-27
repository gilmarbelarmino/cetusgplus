<?php
// Criar tabelas para semanada se não existirem
require_once __DIR__ . '/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS semanada_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255),
            uploaded_by VARCHAR(100),
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS semanada_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL,
            comment_text TEXT NOT NULL,
            parent_id INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parent_id) REFERENCES semanada_comments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "Tabelas criadas com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
