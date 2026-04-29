<?php
require_once 'config.php';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch(Exception $e) {}
}
session_destroy();
header('Location: login.php');
exit;
