<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE loans");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
