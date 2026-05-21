<?php
require 'config.php';

try {
    // 1. Add columns to company_settings table
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL");
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL");
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS radius_meters INT DEFAULT 100");
    $pdo->exec("ALTER TABLE company_settings ADD COLUMN IF NOT EXISTS allow_remote_work TINYINT(1) DEFAULT 0");

    // 2. Create time_records
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        record_type ENUM('Entrada', 'Saida Almoco', 'Retorno Almoco', 'Saida', 'Pausa') NOT NULL,
        record_time DATETIME NOT NULL,
        latitude DECIMAL(10,8),
        longitude DECIMAL(11,8),
        address TEXT,
        ip_address VARCHAR(50),
        device_info VARCHAR(255),
        gps_accuracy FLOAT,
        photo_base64 LONGTEXT,
        status ENUM('Aprovado', 'Pendente', 'Rejeitado', 'Ocorrencia') DEFAULT 'Pendente',
        confidence_score FLOAT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Create time_incidents
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_incidents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        record_id INT NULL,
        incident_date DATE NOT NULL,
        incident_type ENUM('Atraso', 'Falta', 'Hora Extra', 'Saida Antecipada', 'Fraude', 'Fora do Raio', 'Outro') NOT NULL,
        description TEXT,
        time_amount_minutes INT DEFAULT 0,
        status ENUM('Pendente', 'Justificado', 'Descontado') DEFAULT 'Pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (record_id) REFERENCES time_records(id) ON DELETE SET NULL
    )");

    // 4. Create time_bank
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_bank (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        reference_month VARCHAR(7),
        balance_minutes INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // 5. Create vacations
    $pdo->exec("CREATE TABLE IF NOT EXISTS vacations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('Solicitado', 'Aprovado', 'Rejeitado') DEFAULT 'Solicitado',
        approved_by VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 6. Create medical_certificates
    $pdo->exec("CREATE TABLE IF NOT EXISTS medical_certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        issue_date DATE NOT NULL,
        days_off INT NOT NULL,
        file_base64 LONGTEXT NOT NULL,
        status ENUM('Pendente', 'Aprovado', 'Rejeitado') DEFAULT 'Pendente',
        approved_by VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Database tables and columns created successfully.\n";
} catch(Exception $e) {
    echo "Error updating DB: " . $e->getMessage() . "\n";
}
