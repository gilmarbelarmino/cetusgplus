<?php
require 'config.php';

// Migrar borrower_id
$loans = $pdo->query("SELECT id, borrower_name FROM loans WHERE borrower_id IS NULL OR borrower_id = ''")->fetchAll();
$countB = 0;
foreach ($loans as $l) {
    $u = $pdo->prepare("SELECT id FROM users WHERE name = ? LIMIT 1");
    $u->execute([$l['borrower_name']]);
    $user = $u->fetch();
    if ($user) {
        $up = $pdo->prepare("UPDATE loans SET borrower_id = ? WHERE id = ?");
        $up->execute([$user['id'], $l['id']]);
        $countB++;
    }
}

// Migrar received_by_id (opcional, mas bom pra consistência)
$loansR = $pdo->query("SELECT id, received_by FROM loans WHERE (received_by_id IS NULL OR received_by_id = '') AND received_by IS NOT NULL AND received_by != ''")->fetchAll();
$countR = 0;
foreach ($loansR as $l) {
    $u = $pdo->prepare("SELECT id FROM users WHERE name = ? LIMIT 1");
    $u->execute([$l['received_by']]);
    $user = $u->fetch();
    if ($user) {
        $up = $pdo->prepare("UPDATE loans SET received_by_id = ? WHERE id = ?");
        $up->execute([$user['id'], $l['id']]);
        $countR++;
    }
}

echo "Migração concluída: {$countB} IDs de solicitantes e {$countR} IDs de recebedores atualizados.\n";
?>
