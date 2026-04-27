<?php
require 'config.php';
$stmt = $pdo->query("SELECT l.id, l.borrower_id, l.borrower_name, bor.id as b_id, bor.name, bor.avatar_url FROM loans l LEFT JOIN users bor ON BINARY l.borrower_id = BINARY bor.id LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
