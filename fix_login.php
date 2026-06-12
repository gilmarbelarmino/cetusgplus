<?php
require 'config.php';

try {
    // Reativar todos os usuários inativos
    $pdo->exec("UPDATE users SET status = 'Ativo' WHERE status = 'Inativo'");
    
    // Reativar todas as empresas inativas
    $pdo->exec("UPDATE tenants SET status = 'active', deleted_at = NULL, purge_after = NULL WHERE status = 'inactive'");
    
    // Corrigir possíveis datas de término zeradas que causam inativação automática no auth.php
    $pdo->exec("UPDATE rh_employee_details SET end_date = NULL WHERE end_date = '0000-00-00' OR end_date < '2000-01-01'");
    
    echo "<h1>Recuperacao concluida com sucesso!</h1>";
    echo "<p>Todos os usuarios e empresas foram reativados.</p>";
    echo "<p><a href='index.php'>Clique aqui para voltar ao login</a></p>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
