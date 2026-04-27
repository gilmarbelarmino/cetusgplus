<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN tech_password VARCHAR(50) DEFAULT '1968'");
    echo 'Migration Success';
} catch(Exception $e) {
    echo 'Migration Error/Exists: ' . $e->getMessage();
}
?>
