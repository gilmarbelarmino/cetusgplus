<?php
require_once 'config.php';
$res = $pdo->query("SELECT status, COUNT(*) FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
print_r($res);
?>
