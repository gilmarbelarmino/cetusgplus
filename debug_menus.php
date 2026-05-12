<?php
require_once 'config.php';
require_once 'auth.php';
$user = getCurrentUser();
$menus = getUserMenus($user);
echo "User: " . ($user['login_name'] ?? 'N/A') . "\n";
echo "Role: " . ($user['role'] ?? 'N/A') . "\n";
echo "Menus: " . implode(', ', $menus) . "\n";
?>
