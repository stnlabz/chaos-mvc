<?php
/**
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class llms extends controller {
    public static $is_core = true;
    
    public function index() {
        $this->require_admin(9);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return false;
        }

        $this->verify_csrf();
        $pages = $this->model('modules_model')->get_all();
        $host = URLROOT;

        $excluded = ['admin.php', 'auth.php', 'health.php', 'sentinel.php', 'modules.php', 'ror.php', 'llms.php', 'sitemap.php', 'error_handler.php', 'media.php', 'accounts.php', 'traffic.php'];
        /* [Human:Mei | 2026-03-11 02:20:00 UTC] */
        //$controllers = array_diff(scandir(APPROOT . '/controllers'), array_merge(['.', '..'], $excluded));
        /* Force lowercase */
        $files = array_map('strtolower', scandir(APPROOT . '/controllers'));
        $controllers = array_diff($files, array_merge(['.', '..'], $excluded));

        $txt = "# Poe Mei Map\n\n## Controllers\n";
        foreach ($controllers as $file) {
            $name = str_replace('.php', '', $file);
            $safeName = str_replace(['[', ']', '(', ')'], '', $name);
            $txt .= "- [$safeName]($host/" . rawurlencode($name) . ")\n";
        }

        $txt .= "\n## Modules\n";
        foreach ($pages as $p) {
            $title = str_replace(['[', ']', '(', ')', "\r", "\n"], '', $p['title']);
            $txt .= "- [$title]($host/" . rawurlencode($p['slug']) . ")\n";
        }

        file_put_contents(PUBROOT . '/llms.txt', $txt);
        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
