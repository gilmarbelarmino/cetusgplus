<?php
require_once 'peixinho_api.php';

// Mock PDO or use real one if available
try {
    $brain = new PeixinhoBrain($pdo);
    
    $queries = [
        "olá",
        "como usar o sistema?",
        "resumo geral",
        "quem são os voluntários?",
        "quais salas estão livres hoje?",
        "o que andam falando na semanada?",
        "quem é André"
    ];

    echo "--- TESTANDO O CÉREBRO DO PEIXINHO ---\n\n";

    foreach ($queries as $q) {
        echo "USUÁRIO: $q\n";
        echo "PEIXINHO: " . $brain->process($q) . "\n";
        echo "------------------------------------\n";
    }

} catch (Exception $e) {
    echo "Erro no teste: " . $e->getMessage();
}
?>
