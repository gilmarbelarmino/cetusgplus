<?php
session_start();
if (isset($_POST['action']) && $_POST['action'] === 'dismiss_birthday') {
    $_SESSION['bd_shown_v3'] = true;
    echo json_encode(['status' => 'success']);
}
