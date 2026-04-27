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

    protected function query($sql, $params = []) {
        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function all($table) {
        return self::$db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function find($table, $id) {
        $stmt = self::$db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
