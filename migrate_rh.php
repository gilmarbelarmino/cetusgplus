<?php
require_once __DIR__ . '/config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_employee_details (
        user_id VARCHAR(50) PRIMARY KEY,
        contract_type VARCHAR(100) DEFAULT '',
        work_days VARCHAR(100) DEFAULT '',
        work_hours VARCHAR(100) DEFAULT '',
        start_date DATE NULL,
        end_date DATE NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "rh_employee_details OK\n";
} catch (Exception $e) {
    echo "Erro 1: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_vacations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        reference_year INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        limit_date DATE NOT NULL,
        status VARCHAR(50) DEFAULT 'Programada',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "rh_vacations OK\n";
} catch (Exception $e) {
    echo "Erro 2: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        issue_date DATE NOT NULL,
        days_off INT NOT NULL,
        reason VARCHAR(255),
        file_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "rh_certificates OK\n";
} catch (Exception $e) {
    echo "Erro 3: " . $e->getMessage() . "\n";
}
