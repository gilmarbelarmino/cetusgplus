<?php

namespace App\Core;

class Controller {
    protected function view($name, $data = []) {
        extract($data);
        $viewPath = __DIR__ . "/../Views/{$name}.view.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \Exception("View não encontrada: $name");
        }
    }

    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect($url) {
        header("Location: /cetusg{$url}");
        exit;
    }
}
