<?php
require_once 'config.php';
$res = $pdo->exec("UPDATE tickets SET status = 'Concluído' WHERE status IS NULL OR status NOT IN ('Aberto', 'Pendente', 'Sem Solução')");
echo "Final migration: $res rows updated to 'Concluído'.\n";
?>
