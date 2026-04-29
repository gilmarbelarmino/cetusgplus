<?php
require_once 'config.php';
require_once 'auth.php';

echo "<h1>Diagnóstico de Chat</h1>";

// 1. Verificar Sessão
if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "<p style='color:green;'>Logado como: <b>" . $user['name'] . "</b> (ID: " . $user['id'] . ")</p>";
} else {
    echo "<p style='color:red;'>NÃO LOGADO</p>";
}

// 2. Verificar Tabelas
$tables = ['chat_messages', 'chat_clears', 'users'];
foreach($tables as $t) {
    try {
        $q = $pdo->query("DESCRIBE $t");
        echo "<p style='color:green;'>Tabela <b>$t</b> existe.</p>";
    } catch(Exception $e) {
        echo "<p style='color:red;'>Tabela <b>$t</b> MISSING: " . $e->getMessage() . "</p>";
    }
}

// 3. Verificar Permissões Pasta Uploads
$uploadDir = __DIR__ . '/uploads/chat_files/';
if (is_dir($uploadDir)) {
    echo "<p style='color:green;'>Diretório de uploads existe.</p>";
    if (is_writable($uploadDir)) {
        echo "<p style='color:green;'>Diretório de uploads TEM permissão de escrita.</p>";
    } else {
        echo "<p style='color:orange;'>Diretório de uploads SEM permissão de escrita.</p>";
    }
} else {
    echo "<p style='color:orange;'>Diretório de uploads não existe (será criado no primeiro upload).</p>";
}

// 4. Teste de Mensagem Recente
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM chat_messages");
    $count = $stmt->fetchColumn();
    echo "<p>Total de mensagens no banco: <b>$count</b></p>";
    
    $stmt = $pdo->query("SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT 5");
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Últimas 5 mensagens:</h3><ul>";
    foreach($msgs as $m) {
        echo "<li>De: {$m['sender_id']} Para: {$m['receiver_id']} - Conteúdo: {$m['content']} ({$m['created_at']})</li>";
    }
    echo "</ul>";
} catch(Exception $e) {
    echo "<p style='color:red;'>Erro ao ler mensagens: " . $e->getMessage() . "</p>";
}
?>
