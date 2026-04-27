<?php
require 'config.php';
$stmt = $pdo->query('DESCRIBE tickets');
file_put_contents('tickets_schema.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
?>
