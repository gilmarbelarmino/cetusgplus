<?php
require 'config.php';
$stmt = $pdo->query("SHOW FULL COLUMNS FROM users WHERE Field='id'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
