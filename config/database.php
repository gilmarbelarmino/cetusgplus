<?php
/**
 * Configuração de Banco de Dados - Cetusg
 * ========================================
 * Centralização de credenciais e conexão PDO.
 * 
 * Em produção, estas credenciais devem vir de variáveis de ambiente.
 */

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'dbname' => getenv('DB_NAME') ?: 'cetusg_plus',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
    'timezone' => '-03:00',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
