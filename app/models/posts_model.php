<?php
// path: /app/models/posts_model.php

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class posts_model extends model {

    protected $table = 'posts';

    public function get_all() {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY created_at DESC"
        )->fetchAll();
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
            WHERE p.slug = ? AND p.published = 1 AND p.is_active = 1
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
                WHERE p.published = 1 AND p.is_active = 1
                ORDER BY p.created_at DESC";
        return $this->db->query($sql)->fetchAll();
    }
    
    public function get_comments_by_post($post_id)
    {
        $sql = "
            SELECT *
            FROM comments
            WHERE post_id = ? AND is_approved = 1
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

    public function is_public_post(int $id): bool
    {
        return (bool) $this->fetch(
            "SELECT id FROM posts WHERE id = ? AND published = 1 AND is_active = 1 LIMIT 1",
            [$id]
        );
    }
}
/* [End AI:GPT-5.6 Sol] */
