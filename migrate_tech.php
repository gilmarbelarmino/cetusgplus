<?php
require_once 'config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tech_cameras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        quantity INT DEFAULT 1,
        ip_address VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tech_remote_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50),
        pc_password VARCHAR(255),
        email_password VARCHAR(255),
        pc_name VARCHAR(255),
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tech_emails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255),
        type VARCHAR(100),
        remote_user_id VARCHAR(50),
        usage_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Tables tech_cameras, tech_remote_access, tech_emails created successfully.";
} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
?>
