<?php
/**
 * ErrorLogger - Captura exceções e erros fatais globais.
 */
class ErrorLogger {
    private static $logFile = __DIR__ . '/system_errors.log';

    public static function init() {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }

    public static function log($message, $level = 'ERROR') {
        $date = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        
        $logMessage = "[{$date}] [{$level}] [IP: {$ip}] [URI: {$uri}] {$message}" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
        
        // Futuro: Disparo pro Telegram
        // self::sendToTelegram($logMessage);
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) return false;
        self::log("Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}", 'WARNING');
        return false; // let default PHP handler execute too
    }

    public static function handleException($exception) {
        self::log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine(), 'CRITICAL');
        // Se for requisição AJAX/API, responde em JSON limpo em vez de quebrar a tela HTML
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'], 'api_') !== false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ocorreu um erro interno crítico. A equipe técnica foi notificada.']);
            exit;
        }
    }

    public static function handleFatalError() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::log("Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}", 'FATAL');
        }
    }
}

// Inicializa o logger automaticamente
ErrorLogger::init();
?>
