<?php

namespace App\Core;

class Logger {
    public static function log($message, $level = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $logEntry = "[$date] [$level]: $message" . PHP_EOL;
        $logFile = __DIR__ . '/../../storage/logs/app.log';
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    public static function audit($userId, $action, $details = '') {
        // Aqui futuramente podemos salvar em uma tabela `audit_logs` no banco
        self::log("User ID: $userId | Action: $action | Details: $details", 'AUDIT');
    }
}
