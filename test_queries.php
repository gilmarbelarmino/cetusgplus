<?php
require_once 'config.php';

try {
    $remotes = $pdo->query("SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email FROM tech_remote_access tr LEFT JOIN users u ON tr.user_id = u.id ORDER BY u.name")->fetchAll();
    echo "Query 1 OK. Count: " . count($remotes) . "\n";
    
    $emails = $pdo->query("SELECT te.*, u.name as user_name FROM tech_emails te LEFT JOIN users u ON te.remote_user_id = u.id ORDER BY te.email")->fetchAll();
    echo "Query 2 OK. Count: " . count($emails) . "\n";
    
    echo "Success! No collation errors.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
