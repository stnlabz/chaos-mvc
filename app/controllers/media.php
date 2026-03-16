<?php
// path: /app/controllers/media.php

class media extends controller {
    public static $is_core = true;

    public function admin($params = []) {
        $model = $this->model('media_model');
        $action = $params[1] ?? null;
        $id = $params[2] ?? null;

        // DELETE
        if ($action === 'delete' && $id) {
            $item = $model->get_by_id($id);
            if ($item) {
                // Use PUBROOT for physical deletion
                $file = PUBROOT . $item['file_path'];
                if (is_file($file)) unlink($file);
                $model->delete('media', "id = " . (int)$id);
            }
            header("Location: /admin/media");
            exit;
        }

        // UPLOAD
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $upload_dir = '/uploads/';
            $target_dir = PUBROOT . $upload_dir;

            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $filename = time() . '_' . basename($_FILES['file']['name']);
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                $payload = [
                    'filename'  => $_FILES['file']['name'],
                    'file_path' => $upload_dir . $filename, // Stores "/uploads/..."
                    'file_type' => $_FILES['file']['type']
                ];

                $model->insert('media', $payload);
                header("Location: /admin/media");
                exit;
            }
        }

        $data['items'] = $model->get_all();
        $this->view('admin/media', $data);
    }
}
