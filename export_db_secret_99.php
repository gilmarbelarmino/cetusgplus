<?php
require 'config.php';
// Ignorar no ambiente local, forçar dump da nuvem
if ($isLocal && !isset($_GET['force'])) {
    die("Script must run on production.");
}

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="backup.sql"');

try {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        echo "DROP TABLE IF EXISTS `$table`;\n";
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
        
        $rows = $pdo->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $keys = array_keys($row);
            $keys = array_map(function($k) { return "`$k`"; }, $keys);
            
            $vals = array_values($row);
            $vals = array_map(function($v) use ($pdo) {
                if ($v === null) return "NULL";
                return $pdo->quote($v);
            }, $vals);
            
            echo "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
} catch (Exception $e) {
    echo "/* ERROR: " . $e->getMessage() . " */";
}
