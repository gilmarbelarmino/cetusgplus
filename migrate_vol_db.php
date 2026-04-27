<?php
require_once 'config.php';
try {
    $pdo->exec("ALTER TABLE volunteers ADD COLUMN cpf VARCHAR(20) DEFAULT NULL AFTER name");
    echo "Added 'cpf' column to 'volunteers'.\n";
} catch(Exception $e) { echo "Note: 'cpf' column might already exist.\n"; }

try {
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN certificate_signature_url VARCHAR(255) DEFAULT NULL");
    echo "Added 'certificate_signature_url' column to 'company_settings'.\n";
} catch(Exception $e) { echo "Note: 'certificate_signature_url' column might already exist.\n"; }
?>
