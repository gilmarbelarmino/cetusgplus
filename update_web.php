<?php
// Script de atualização forçada para ambiente Web
require_once 'config.php';

echo "<h2>Iniciando Atualização do Banco de Dados (Web)...</h2>";

try {
    // Tabela de Pesquisas
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        company_id INT NOT NULL,
        created_by VARCHAR(50),
        status ENUM('Ativa', 'Encerrada') DEFAULT 'Ativa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Tabela de Perguntas
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_text TEXT NOT NULL,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Tabela de Opções
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Tabela de Respostas
    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_id INT NOT NULL,
        option_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES survey_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES survey_options(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Correção do Patrimônio (Type Mismatch)
    $pdo->exec("ALTER TABLE assets MODIFY responsible_id VARCHAR(50) NULL");
    $pdo->exec("UPDATE assets SET responsible_id = NULL WHERE responsible_id = '0'");
    echo "<p style='color: green;'>✅ Estrutura de patrimônio (responsible_id) corrigida!</p>";

    echo "<p style='color: green;'>✅ Tabelas de pesquisa verificadas/criadas com sucesso!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao atualizar banco: " . $e->getMessage() . "</p>";
}

echo "<p>Por favor, recarregue a página principal do sistema (Ctrl + F5) para ver as mudanças.</p>";
?>
