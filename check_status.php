<?php
require_once 'config.php';
$res = $pdo->query('SELECT status, COUNT(*) as count FROM tickets GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
