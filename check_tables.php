<?php
require 'config.php';
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) echo "$t\n";
