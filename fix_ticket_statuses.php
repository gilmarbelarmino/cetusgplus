<?php
require_once 'config.php';

try {
    // 1. Corrigir tickets com status vazio para 'Concluído' (Lógica: se não está aberto nem pendente, e está no sistema há tempo, era considerado resolvido na versão anterior)
    $stmt1 = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = '' OR status IS NULL");
    $stmt1->execute();
    $count1 = $stmt1->rowCount();

    // 2. Mapear status legados se existirem
    $stmt2 = $pdo->prepare("UPDATE tickets SET status = 'Concluído' WHERE status = 'Finalizado' OR status = 'Resolvido'");
    $stmt2->execute();
    $count2 = $stmt2->rowCount();

    echo "Migração concluída:\n";
    echo "- $count1 tickets com status vazio corrigidos para 'Concluído'.\n";
    echo "- $count2 tickets legados corrigidos.\n";
} catch (Exception $e) {
    echo "Erro na migração: " . $e->getMessage();
}
?>
