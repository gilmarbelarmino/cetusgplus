<?php
session_start();
if (isset($_POST['action']) && $_POST['action'] === 'dismiss_announcement') {
    $_SESSION['announcement_dismissed'] = true;
    echo json_encode(['success' => true]);
}
?>
