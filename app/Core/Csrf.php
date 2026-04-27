<?php

namespace App\Core;

/**
 * Proteção contra CSRF (Cross-Site Request Forgery)
 * ==================================================
 * Gera e valida tokens únicos para cada formulário,
 * impedindo que sites externos enviem requisições falsas.
 */
class Csrf {

    /**
     * Gera um token CSRF e armazena na sessão
     */
    public static function generate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Retorna um campo hidden HTML pronto para uso em formulários
     */
    public static function field() {
        return '<input type="hidden" name="_csrf_token" value="' . self::generate() . '">';
    }

    /**
     * Valida o token enviado no POST
     * @return bool
     */
    public static function validate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Valida e aborta com erro 403 se inválido
     */
    public static function check() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!self::validate()) {
                http_response_code(403);
                die('<div style="text-align:center;padding:4rem;font-family:sans-serif;">
                    <h1 style="color:#ef4444;">403 - Acesso Negado</h1>
                    <p style="color:#64748b;">Token de segurança inválido ou expirado. Recarregue a página e tente novamente.</p>
                    <a href="javascript:history.back()" style="color:#6366f1;">← Voltar</a>
                </div>');
            }
        }
    }

    /**
     * Regenera o token (útil após login)
     */
    public static function regenerate() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
