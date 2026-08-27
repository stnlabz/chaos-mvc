<?php

/** Serve assets belonging to the active installation theme. */
class theme_assets extends controller
{
    public static $is_core = true;

    public function index($params = []): void
    {
        $path = trim((string) ($_GET['file'] ?? ''));
        $requestedTheme = trim((string) ($_GET['theme'] ?? ''));
        $file = theme::activeAssetFile($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
        ];

        if (
            $requestedTheme === ''
            || !hash_equals(theme::activeSlug(), $requestedTheme)
            || $file === null
            || !isset($types[$extension])
        ) {
            (new error_handler())->not_found();
        }

        header('Content-Type: ' . $types[$extension]);
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }
}
