<?php
require 'config.php';
echo "--- users ---\n";
$stmt = $pdo->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "--- rh_employee_details ---\n";
$stmt = $pdo->query("DESCRIBE rh_employee_details");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
