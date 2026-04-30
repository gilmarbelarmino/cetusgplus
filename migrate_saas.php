<?php
require_once 'config.php';

try {
    // 1. Criar tabela de Tenants com campos financeiros e de datas
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        domain VARCHAR(100) UNIQUE,
        status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
        license_type ENUM('monthly', 'yearly', 'lifetime') DEFAULT 'monthly',
        expires_at DATE,
        subscription_value DECIMAL(10,2) DEFAULT 0.00,
        last_amount_paid DECIMAL(10,2) DEFAULT 0.00,
        last_payment_date DATETIME,
        access_liberation_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Inserir tenant padrão
    $pdo->exec("INSERT IGNORE INTO tenants (id, name, status, license_type, expires_at) VALUES (1, 'Cetusg Principal', 'active', 'lifetime', '2099-12-31')");

    // 3. Adicionar colunas de Super Admin e login na tabela users
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin TINYINT(1) DEFAULT 0");
    } catch(Exception $e) {}

    // 4. Criar o usuário Super Admin Mestre Definitivo
    // Usamos o ID 'SUPER_ADMIN_01' para unicidade
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, login_name, name, email, password, is_super_admin, company_id, status) 
                           VALUES ('SUPER_ADMIN_01', 'superadmin', 'Administrador Master', 'master@cetusg.com', '29561308', 1, 1, 'Ativo')");
    $stmt->execute();

    echo "<div style='font-family:Arial; padding:3rem; text-align:center; background:#f8fafc;'>
            <h1 style='color:#10B981; font-size:2.5rem;'>✅ Cetusg SaaS Ativado!</h1>
            <p style='font-size:1.2rem; color:#64748b;'>O banco de dados foi atualizado e o Super Administrador Mestre foi criado.</p>
            <div style='background:white; padding:2rem; border-radius:15px; display:inline-block; margin-top:2rem; border:1px solid #e2e8f0;'>
                <p><strong>Usuário:</strong> superadmin</p>
                <p><strong>Senha:</strong> 29561308</p>
            </div>
            <br><br>
            <a href='index.php' style='display:inline-block; background:#4F46E5; color:white; padding:1rem 2rem; border-radius:10px; text-decoration:none; font-weight:bold;'>Acessar Sistema</a>
          </div>";

} catch (Exception $e) {
    echo "<h1 style='color:#EF4444;'>❌ Erro na Migração SaaS:</h1>" . $e->getMessage();
}
?>
