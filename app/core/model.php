<?php
// path: /app/core/model.php

class model {
    protected $db;

    public function __construct() {
        require_once APPROOT . '/core/config.php';

        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        try {
            $this->db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database connection failed.");
        }
    }

    /* =========================
       Core Query Wrapper
    ========================= */

    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /* =========================
       Insert Helper
    ========================= */

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(", ", $keys);
        $placeholders = ":" . implode(", :", $keys);

        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);

        return $this->db->lastInsertId();
    }

    /* =========================
       Update Helper
    ========================= */

    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
        }

        $fieldList = implode(', ', $fields);

        $sql = "UPDATE $table SET $fieldList WHERE $where";

        return $this->query($sql, array_merge($data, $whereParams));
    }

    /* =========================
       Archive (Replaces delete)
    ========================= */

    public function archive($table, $where, $params = []) {
        $sql = "UPDATE $table SET is_active = 0 WHERE $where";
        return $this->query($sql, $params);
    }
    
    /* =========================
       Restore to take a thing 
       out of archive
   ========================== */
   
   public function restore($table, $where, $params = []) {
       $sql = "UPDATE $table SET is_active = 1 WHERE $where";
       return $this->query($sql, $params);
   }

    /* =========================
       Exists Helper
    ========================= */

    public function exists($table, $where, $params = []) {
        $sql = "SELECT 1 FROM $table WHERE $where LIMIT 1";
        return (bool) $this->fetch($sql, $params);
    }
    
    /* =========================
       Fetch Helper
    ========================= */
    
    public function fetch(string $sql, array $params = [])
    {
         $stmt = $this->query($sql, $params);
         return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /* =========================
       fetchAll Helper
    ========================= */

    public function fetchAll(string $sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

