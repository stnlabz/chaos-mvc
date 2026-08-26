<?php
// path: /app/controllers/media.php

class media extends controller {
    public static $is_core = true;

    public function admin($params = []) {
        $this->require_admin(7);
        $model = $this->model('media_model');
        $action = $params[1] ?? null;
        $id = $params[2] ?? null;

        // DELETE
        if ($action === 'delete' && $id) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                $this->error_page('Media deletion requires POST.');
            }

            $this->verify_csrf();
            $item = $model->get_by_id($id);
            if ($item) {
                $uploadRoot = realpath(PUBROOT . '/uploads');
                $file = realpath(PUBROOT . (string) $item['file_path']);
                if ($uploadRoot !== false && $file !== false
                    && str_starts_with($file, $uploadRoot . DIRECTORY_SEPARATOR)
                    && is_file($file)) {
                    unlink($file);
                }
                $model->delete_by_id((int) $id);
            }
            header("Location: /admin/media");
            exit;
        }

        // UPLOAD
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $this->verify_csrf();
            $upload_dir = '/uploads/';
            $target_dir = PUBROOT . $upload_dir;

            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $file = $_FILES['file'];
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || (int) ($file['size'] ?? 0) < 1
                || (int) $file['size'] > 10485760) {
                $this->error_page('Uploads must be images no larger than 10 MB.');
            }

            $temporary = (string) ($file['tmp_name'] ?? '');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
            if (!is_string($mime) || !isset($allowed[$mime])) {
                $this->error_page('Unsupported upload type.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($temporary, $target_file)) {
                $payload = [
                    'filename'  => basename((string) $file['name']),
                    'file_path' => $upload_dir . $filename,
                    'file_type' => $mime
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
