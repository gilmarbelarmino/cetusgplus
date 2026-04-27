<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, name, avatar_url FROM users LIMIT 10");
while ($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | Name: {$row['name']} | Avatar: {$row['avatar_url']}\n";
}
?>
