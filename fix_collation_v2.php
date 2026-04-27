<?php
require_once 'config.php';

try {
    // Explicitly set the collation for the linked columns
    $pdo->exec("ALTER TABLE tech_remote_access MODIFY user_id VARCHAR(50) COLLATE utf8mb4_general_ci");
    $pdo->exec("ALTER TABLE tech_emails MODIFY remote_user_id VARCHAR(50) COLLATE utf8mb4_general_ci");

    // Also set the table collation for consistency
    $pdo->exec("ALTER TABLE tech_cameras CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("ALTER TABLE tech_remote_access CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("ALTER TABLE tech_emails CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    echo "Table and column collations aligned to utf8mb4_general_ci successfully.";
} catch (PDOException $e) {
    die("Error aligning collations: " . $e->getMessage());
}
?>
