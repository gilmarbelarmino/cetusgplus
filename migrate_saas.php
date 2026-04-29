<?php
require_once 'config.php';

$tables = [
    'announcements', 'assets', 'audit_logs', 'budget_quotes', 'budget_requests',
    'loans', 'room_bookings', 'rooms', 'sectors', 'tech_cameras', 'tech_emails',
    'tech_note_sections', 'tech_notes', 'tech_remote_access', 'tickets', 'units',
    'volunteers', 'roles', 'permissions'
];

foreach ($tables as $table) {
    try {
        echo "Updating table: $table... ";
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS company_id INT DEFAULT 1 AFTER id, ADD INDEX IF NOT EXISTS idx_company (company_id)");
        echo "OK\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Migration finished.\n";
