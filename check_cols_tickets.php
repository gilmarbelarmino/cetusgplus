<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE tickets");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
