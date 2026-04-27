<?php
require_once 'config.php';
$users = $pdo->query("SELECT login_name FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users);
