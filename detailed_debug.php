<?php
require_once 'config.php';
$res = $pdo->query("SELECT id, status, HEX(status) as hex_status FROM tickets WHERE status NOT IN ('Aberto')")->fetchAll(PDO::FETCH_ASSOC);
echo "Tickets non-Aberto: " . count($res) . "\n";
foreach($res as $r) {
    echo "ID: " . $r['id'] . " | Status: [" . $r['status'] . "] | HEX: [" . $r['hex_status'] . "]\n";
}
?>
