<?php
require_once 'config.php';

try {
    echo "Conexão OK!\n";
    
    echo "\nTabelas encontradas:\n";
    $stmt = $pdo->query("SHOW TABLES");
    while($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }

    echo "\nColunas da tabela 'ticket_pauses':\n";
    try {
        $stmt = $pdo->query("DESCRIBE ticket_pauses");
        while($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Tabela 'ticket_pauses' não encontrada.\n";
    }

    echo "\nColunas da tabela 'tech_cameras':\n";
    try {
        $stmt = $pdo->query("DESCRIBE tech_cameras");
        while($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Tabela 'tech_cameras' não encontrada.\n";
    }

} catch (Exception $e) {
    echo "Erro na conexão: " . $e->getMessage() . "\n";
}
