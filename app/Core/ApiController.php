<?php

namespace App\Core;

use App\Core\Controller;
use App\Core\Auth;

class ApiController extends Controller {
    
    public function __construct() {
        // Garantir que a resposta seja sempre JSON
        header('Content-Type: application/json; charset=utf-8');
        
        // Proteção básica: Se não estiver logado, não acessa a API
        if (!Auth::check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Não autorizado. Por favor, realize o login.'
            ], 401);
            exit;
        }
    }

    /**
     * Envia uma resposta JSON padronizada
     */
    protected function jsonResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Helper para sucesso
     */
    protected function success($data = [], $message = "Operação realizada com sucesso") {
        $this->jsonResponse([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }

    /**
     * Helper para erro
     */
    protected function error($message = "Erro na operação", $code = 400, $errors = []) {
        $this->jsonResponse([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $code);
    }
}
