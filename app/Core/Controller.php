<?php

namespace App\Core;

class Controller {
    protected function view($name, $data = [], $layout = 'main') {
        extract($data);
        $viewContent = __DIR__ . "/../Views/{$name}.view.php";
        
        if (!file_exists($viewContent)) {
            throw new \Exception("View não encontrada: $name");
        }

        if ($layout) {
            $layoutPath = __DIR__ . "/../Views/layouts/{$layout}.view.php";
            if (file_exists($layoutPath)) {
                require $layoutPath;
                return;
            }
        }

        require $viewContent;
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
