<?php

namespace App\Core;

use PDO;

class Model {
    protected static $db;

    public static function setConnection($pdo) {
        self::$db = $pdo;
    }

    public static function getConnection() {
        return self::$db;
    }

    public static function getCompanyId() {
        return $_SESSION['company_id'] ?? 1;
    }

    protected function query($sql, $params = []) {
        // Se a query não tiver filtros de empresa e for um SELECT simples em uma tabela
        // poderíamos automatizar, mas por segurança usaremos o helper manual inicialmente
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Retorna todos os registros filtrados pela empresa atual
     */
    protected function all($table) {
        $stmt = self::$db->prepare("SELECT * FROM $table WHERE company_id = ?");
        $stmt->execute([self::getCompanyId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function find($table, $id) {
        $stmt = self::$db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
