<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE loans");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}
?>
