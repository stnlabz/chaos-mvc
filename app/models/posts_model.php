<?php
// path: /app/models/posts_model.php

class posts_model extends model {

    protected $table = 'posts';

    public function get_all() {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY created_at DESC")->fetchAll();
    }

    public function get_by_id($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function get_post_with_image($slug)
    {
        // Fixed: JOIN on featured_image_id to link the post to its media file
        $sql = "
            SELECT p.*, m.file_path AS image_path
            FROM posts p
            LEFT JOIN media m ON m.id = p.featured_image_id
            WHERE p.slug = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function get_public_feed() {
        $sql = "SELECT p.*, m.file_path as image_path 
                FROM posts p 
                LEFT JOIN media m ON p.featured_image_id = m.id 
                WHERE p.published = 1 
                ORDER BY p.created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }
    
    public function get_comments_by_post($post_id)
    {
        $sql = "
            SELECT *
            FROM comments
            WHERE post_id = ?
            ORDER BY created_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$post_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update_post($id, $data) {
        $sql = "UPDATE {$this->table} SET title = :title, slug = :slug, body = :body, published = :published WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'title'     => $data['title'],
            'slug'      => $data['slug'],
            'body'      => $data['body'],
            'published' => (int)$data['published'],
            'id'        => (int)$id
        ]);
    }

    /**
     * REAL DELETE: Replaces the 'archive' behavior.
     * When the controller calls $model->archive(), it now nukes the row.
     */
    public function archive($table = null, $where = null, $params = []) {
        $id = $params['id'] ?? $where;
        return $this->delete_post($id); 
    }

    public function delete_post($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => (int)$id]);
    }
}
