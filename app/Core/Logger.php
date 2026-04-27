<?php

namespace App\Core;

class Logger {
    private static $pdo;

    /**
     * Injeta a conexão PDO para logs no banco de dados
     */
    public static function setConnection($pdo) {
        self::$pdo = $pdo;
    }

    /**
     * Log genérico em arquivo (erros, debug)
     */
    public static function log($message, $level = 'INFO') {
        $date = date('Y-m-d H:i:s');
        $logEntry = "[$date] [$level]: $message" . PHP_EOL;
        $logDir = __DIR__ . '/../../storage/logs';
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/app_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Log de auditoria no banco de dados (LGPD, rastreabilidade)
     * Registra quem fez o quê, quando e de onde.
     */
    public static function audit($action, $module, $details = '', $userId = null, $userName = null) {
        // Tentar pegar dados da sessão se não fornecidos
        if ($userId === null) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        if ($userName === null) {
            $userName = $_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Sistema';
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Salvar no banco
        if (self::$pdo) {
            try {
                $stmt = self::$pdo->prepare(
                    "INSERT INTO audit_logs (user_id, user_name, action, module, details, ip_address) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId, $userName, $action, $module, $details, $ip]);
            } catch (\Exception $e) {
                // Se falhar no banco, registrar no arquivo como fallback
                self::log("AUDIT FALLBACK - User: $userName | Action: $action | Module: $module | Details: $details", 'AUDIT');
            }
        }

        // Também registrar no arquivo para backup
        self::log("User: $userName (ID: $userId) | Action: $action | Module: $module | Details: $details | IP: $ip", 'AUDIT');
    }

    /**
     * Log de erro
     */
    public static function error($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        self::log($message . $contextStr, 'ERROR');
    }

    /**
     * Log de acesso a módulos sensíveis
     */
    public static function access($module, $userId = null) {
        self::audit('access', $module, 'Módulo acessado', $userId);
    }
}
