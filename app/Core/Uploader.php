<?php

namespace App\Core;

/**
 * Uploader - Gestão Profissional de Arquivos
 * =========================================
 * Gerencia o upload de arquivos, validação de tipos,
 * redimensionamento (opcional) e armazenamento em disco.
 */
class Uploader {
    protected static $baseDir = __DIR__ . '/../../public/uploads';
    protected static $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'xlsx'];
    protected static $maxSize = 5242880; // 5MB

    /**
     * Processa o upload de um arquivo vindo do $_FILES
     */
    public static function upload($file, $subDir = '') {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \Exception('Parâmetros de arquivo inválidos.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_NO_FILE: throw new \Exception('Nenhum arquivo enviado.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: throw new \Exception('Limite de tamanho excedido.');
            default: throw new \Exception('Erro desconhecido no upload.');
        }

        if ($file['size'] > self::$maxSize) {
            throw new \Exception('Arquivo muito grande (máx 5MB).');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowedExtensions)) {
            throw new \Exception('Tipo de arquivo não permitido.');
        }

        $dir = rtrim(self::$baseDir . '/' . ltrim($subDir, '/'), '/');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
        $targetPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \Exception('Falha ao mover arquivo enviado.');
        }

        // Retorna o caminho relativo para salvar no banco
        return '/uploads/' . ltrim($subDir . '/' . $filename, '/');
    }

    /**
     * Converte imagens Base64 de um conteúdo HTML para arquivos físicos
     * Útil para o editor Quill
     */
    public static function processHtmlBase64($html, $subDir = 'editor') {
        $dom = new \DOMDocument();
        // Evitar erros com HTML5
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        $changed = false;

        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            
            // Verifica se é Base64
            if (preg_match('/^data:image\/(\w+);base64,/', $src, $type)) {
                $changed = true;
                $type = strtolower($type[1]); // jpg, png, etc
                
                $data = substr($src, strpos($src, ',') + 1);
                $data = base64_decode($data);

                $dir = self::$baseDir . '/' . $subDir;
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                $filename = sprintf('img_%s_%s.%s', date('His'), bin2hex(random_bytes(4)), $type);
                file_put_contents($dir . '/' . $filename, $data);

                $img->setAttribute('src', '/cetusg/public/uploads/' . $subDir . '/' . $filename);
            }
        }

        return $changed ? $dom->saveHTML() : $html;
    }
}
