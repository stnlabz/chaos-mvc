<?php

/**
 * Media controller.
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class media extends controller
{
    public static $is_core = true;

    /**
     * Manage uploaded media.
     *
     * @param array $params Route parameters.
     */
    public function admin($params = []): void
    {
        $this->require_admin(7);

        $model = $this->model('media_model');
        $action = $params[1] ?? null;
        $id = isset($params[2]) ? (int) $params[2] : 0;

        if ($action === 'delete') {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                $this->error_page('Media deletion requires a POST request.');
            }

            $this->verify_csrf();
            $item = $id > 0 ? $model->get_by_id($id) : false;

            if ($item) {
                $uploadRoot = realpath(PUBROOT . '/uploads');
                $storedFile = realpath(PUBROOT . $item['file_path']);

                if (
                    $uploadRoot !== false &&
                    $storedFile !== false &&
                    str_starts_with(
                        $storedFile,
                        $uploadRoot . DIRECTORY_SEPARATOR
                    ) &&
                    is_file($storedFile)
                ) {
                    unlink($storedFile);
                }

                $model->delete($id);
            }

            header('Location: /admin/media');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $this->verify_csrf();
            $this->upload($model, $_FILES['file']);
        }

        $this->view('admin/media', ['items' => $model->get_all()]);
    }

    /**
     * Validate and store an uploaded image.
     */
    private function upload(media_model $model, array $file): never
    {
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->error_page('The upload did not complete successfully.');
        }

        if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > 10485760) {
            $this->error_page('Uploads must be images no larger than 10 MB.');
        }

        $temporaryFile = $file['tmp_name'] ?? '';
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);

        if (!isset($allowedTypes[$mimeType])) {
            $this->error_page('Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        $uploadDir = '/uploads/';
        $targetDir = PUBROOT . $uploadDir;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $this->error_page('The upload directory could not be created.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        $targetFile = $targetDir . $filename;

        if (!move_uploaded_file($temporaryFile, $targetFile)) {
            $this->error_page('The uploaded image could not be stored.');
        }

        $model->insert('media', [
            'filename' => basename((string) ($file['name'] ?? $filename)),
            'file_path' => $uploadDir . $filename,
            'file_type' => $mimeType
        ]);

        header('Location: /admin/media');
        exit;
    }
}
/* [End AI:GPT-5.6 Sol] */
