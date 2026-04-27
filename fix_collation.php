<?php
require_once 'config.php';

try {
    // Definir collation para as tabelas e colunas afetadas
    $pdo->exec("ALTER TABLE tech_cameras CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE tech_remote_access CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE tech_emails CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "Table collations aligned to utf8mb4_unicode_ci successfully.";
} catch (PDOException $e) {
    die("Error aligning collations: " . $e->getMessage());
}
?>
