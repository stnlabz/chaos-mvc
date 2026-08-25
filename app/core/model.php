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
        $table = $this->quoteIdentifier($table);
        $keys = array_keys($data);
        $fields = implode(", ", array_map([$this, 'quoteIdentifier'], $keys));
        $placeholders = ":" . implode(", :", $keys);

        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, $data);

        return $this->db->lastInsertId();
    }

    /* =========================
       Update Helper
    ========================= */

    public function update($table, $data, $where, $whereParams = []) {
        $table = $this->quoteIdentifier($table);
        $where = $this->validateWhereClause($where);
        $fields = [];

        foreach ($data as $key => $value) {
            $fields[] = $this->quoteIdentifier($key) . " = :$key";
        }

        $fieldList = implode(', ', $fields);

        $sql = "UPDATE $table SET $fieldList WHERE $where";

        return $this->query($sql, array_merge($data, $whereParams));
    }

    /* =========================
       Archive (Replaces delete)
    ========================= */

    public function archive($table, $where, $params = []) {
        $table = $this->quoteIdentifier($table);
        $where = $this->validateWhereClause($where);
        $sql = "UPDATE $table SET is_active = 0 WHERE $where";
        return $this->query($sql, $params);
    }
    
    /* =========================
       Restore to take a thing 
       out of archive
   ========================== */
   
   public function restore($table, $where, $params = []) {
       $table = $this->quoteIdentifier($table);
       $where = $this->validateWhereClause($where);
       $sql = "UPDATE $table SET is_active = 1 WHERE $where";
       return $this->query($sql, $params);
   }

    /* =========================
       Exists Helper
    ========================= */

    public function exists($table, $where, $params = []) {
        $table = $this->quoteIdentifier($table);
        $where = $this->validateWhereClause($where);
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

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL identifier.');
        }

        return '`' . $identifier . '`';
    }

    private function validateWhereClause(string $where): string
    {
        $comparison = '[a-z][a-z0-9_]*\\s*(?:=|!=|<>|<=|>=|<|>)\\s*(?::[a-z][a-z0-9_]*|\\?)';

        if (!preg_match('/^' . $comparison . '(?:\\s+AND\\s+' . $comparison . ')*$/i', trim($where))) {
            throw new InvalidArgumentException('Unsafe WHERE clause.');
        }

        return $where;
    }
}
