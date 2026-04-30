<?php
require_once 'config.php';
$pass = password_hash('29561308', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE login_name = 'superadmin'");
if ($stmt->execute([$pass])) {
    echo "<h1>✅ Senha do superadmin atualizada!</h1>";
    echo "<p>Agora você pode fazer login com a senha: 29561308</p>";
} else {
    echo "<h1>❌ Erro ao atualizar senha.</h1>";
}
?>
