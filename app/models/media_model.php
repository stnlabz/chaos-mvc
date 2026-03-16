<?php
// path: /app/models/media_model.php

class media_model extends model {

    protected $table = 'media';

    public function get_all() {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY created_at DESC")->fetchAll();
    }

    public function get_by_id($id) {
        return $this->db->query("SELECT * FROM {$this->table} WHERE id = ?", [(int)$id])->fetch();
    }

    /**
     * Standard Delete Method
     * Matches the pattern used in your announcements and admin controllers
     */
    public function delete($table, $where) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->db->query($sql);
    }
}
