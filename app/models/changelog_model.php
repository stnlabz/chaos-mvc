<?php
class changelog_model extends model {
    public function get_all_updates() {
        // Fetching updates in descending order so the newest is first
        return $this->db->query("SELECT * FROM changelog ORDER BY date_released DESC, id DESC")->fetchAll();
    }
}
