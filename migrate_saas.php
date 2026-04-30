<?php
require_once 'config.php';

try {
    // 1. Criar tabela de Tenants
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        domain VARCHAR(100) UNIQUE,
        status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
        license_type ENUM('monthly', 'yearly', 'lifetime') DEFAULT 'monthly',
        expires_at DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Inserir tenant padrão se não existir
    $pdo->exec("INSERT IGNORE INTO tenants (id, name, status, license_type, expires_at) VALUES (1, 'Cetusg Principal', 'active', 'lifetime', '2099-12-31')");

    // 3. Adicionar coluna is_super_admin se não existir
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_super_admin TINYINT(1) DEFAULT 0");
    } catch(Exception $e) {
        // Coluna já existe
    }

    // 4. Ativar Super Admin para o administrador
    $pdo->exec("UPDATE users SET is_super_admin = 1 WHERE login_name IN ('admin', 'gil') OR id = '1'");

    echo "<div style='font-family:Arial; padding:2rem; text-align:center;'>
            <h1 style='color:#10B981;'>✅ Migração SaaS Concluída!</h1>
            <p>O banco de dados foi atualizado com sucesso.</p>
            <a href='index.php' style='color:#4F46E5; font-weight:bold;'>Ir para o Sistema</a>
          </div>";

} catch (Exception $e) {
    echo "<h1 style='color:#EF4444;'>❌ Erro na Migração:</h1>" . $e->getMessage();
}
?>
