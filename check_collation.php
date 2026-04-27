<?php
require_once 'config.php';

function getCollation($pdo, $table, $column) {
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table` WHERE Field='$column'");
    $res = $stmt->fetch();
    return $res['Collation'] ?? 'Unknown';
}

echo "users.id: " . getCollation($pdo, 'users', 'id') . "\n";
echo "tech_remote_access.user_id: " . getCollation($pdo, 'tech_remote_access', 'user_id') . "\n";
echo "tech_emails.remote_user_id: " . getCollation($pdo, 'tech_emails', 'remote_user_id') . "\n";
?>
