<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE room_bookings ADD COLUMN status VARCHAR(20) DEFAULT 'Aprovado'");
    echo "Column 'status' added successfully to room_bookings.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'status' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
