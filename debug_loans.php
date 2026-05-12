<?php
require 'config.php';
$stmt = $pdo->query("SELECT l.id, l.borrower_id, l.borrower_name, bor.id as b_id, bor.name, bor.avatar_url FROM loans l LEFT JOIN users bor ON CONVERT(l.borrower_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(bor.id USING utf8mb4) COLLATE utf8mb4_unicode_ci LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
