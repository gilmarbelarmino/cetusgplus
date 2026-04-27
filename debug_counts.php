<?php
require_once 'config.php';
$counts = [
    'assets' => $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn(),
    'tickets' => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'loans' => $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn(),
    'volunteers' => $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn(),
    'room_bookings' => $pdo->query("SELECT COUNT(*) FROM room_bookings")->fetchColumn(),
    'budget_requests' => $pdo->query("SELECT COUNT(*) FROM budget_requests")->fetchColumn(),
];
echo json_encode($counts);
?>
