<?php
require_once __DIR__ . '/config.php';
if (isset($_POST['action']) && $_POST['action'] === 'dismiss_announcement') {
    $_SESSION['announcement_dismissed'] = true;
    echo json_encode(['success' => true]);
}
?>
