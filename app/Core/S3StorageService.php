<?php

namespace App\Core;

/**
 * S3StorageService - Integração Cloudflare R2 Minimalista
 * =======================================================
 * Gerencia envio e exclusão de arquivos no bucket R2 do CetusG
 * usando cURL puro e AWS Signature V4. Livre de dependências (sem vendor).
 */
class S3StorageService {
    private $bucketName = 'cetusgplus';
    private $publicUrl = 'https://pub-bad24f43d1634374a7588e3506f8fcec.r2.dev';
    private $accessKey = 'd6e37a1ce54b5deee37126e6718d8055';
    private $secretKey = '94f73205e0cf41715b4e8891c684c59379e56f39747051009734bbe3442eccb4';
    private $endpoint = 'https://dd1b5ef87ee35dca8be0a255396d57cc.r2.cloudflarestorage.com';
    private $region = 'auto';
    private $service = 's3';

    /**
     * Faz o upload de um arquivo para o R2
     * 
     * @param array $file O arquivo enviado via $_FILES
     * @param string $folder A pasta dentro do bucket (ex: 'avatars')
     * @return string URL pública do arquivo
     * @throws \Exception
     */
    public function uploadFile($file, $folder = 'uploads') {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Erro ao enviar arquivo.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'docx', 'xlsx'];
        
        if (!in_array($ext, $allowed)) {
            throw new \Exception('Formato de arquivo não permitido.');
        }

        $filename = uniqid(date('Ymd_His_')) . '.' . $ext;
        $key = trim($folder, '/') . '/' . $filename;
        $payload = file_get_contents($file['tmp_name']);
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $url = $this->endpoint . '/' . $this->bucketName . '/' . $key;
        
        $headers = $this->createSignature('PUT', '/' . $this->bucketName . '/' . $key, '', $payload, $mimeType);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return rtrim($this->publicUrl, '/') . '/' . $key;
        }

        // Falhou
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error("R2 Upload Error: HTTP $httpCode - Response: $response");
        }
        throw new \Exception('Falha na comunicação com o servidor de armazenamento (R2). HTTP Code: ' . $httpCode);
    }

    /**
     * Remove um arquivo do R2 baseado na URL pública
     * 
     * @param string $fileUrl URL completa do arquivo no R2
     */
    public function deleteFile($fileUrl) {
        if (empty($fileUrl) || strpos($fileUrl, $this->publicUrl) === false) {
            return false;
        }

        $key = ltrim(str_replace(rtrim($this->publicUrl, '/'), '', $fileUrl), '/');
        $url = $this->endpoint . '/' . $this->bucketName . '/' . $key;
        
        $headers = $this->createSignature('DELETE', '/' . $this->bucketName . '/' . $key, '', '');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode == 204 || $httpCode == 200);
    }

    /**
     * Cria os cabeçalhos de autenticação AWS Signature V4
     */
    private function createSignature($method, $uri, $query, $payload, $contentType = 'application/octet-stream') {
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $payloadHash = hash('sha256', $payload);

        $canonicalHeaders = "host:" . parse_url($this->endpoint, PHP_URL_HOST) . "\n";
        $canonicalHeaders .= "x-amz-content-sha256:" . $payloadHash . "\n";
        $canonicalHeaders .= "x-amz-date:" . $amzDate . "\n";
        
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";

        $canonicalRequest = "$method\n$uri\n$query\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "$dateStamp/{$this->region}/{$this->service}/aws4_request";
        $stringToSign = "$algorithm\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $kSecret = "AWS4" . $this->secretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);
        $kSigning = hash_hmac('sha256', "aws4_request", $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "$algorithm Credential={$this->accessKey}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";

        $headers = [
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $amzDate",
            "Authorization: $authHeader"
        ];

        if ($method === 'PUT') {
            $headers[] = "Content-Type: $contentType";
            $headers[] = "Content-Length: " . strlen($payload);
        }

        return $headers;
    }
}
