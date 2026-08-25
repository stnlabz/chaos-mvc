<?php
/**
 * Sitemap Controller
 * Generates sitemap.xml using controller + module discovery.
 * Same discovery logic as llms + ror.
 */
 /**
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class sitemap extends controller
{
    public static $is_core = true;

    public function index()
    {
        $this->require_admin(9);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return false;
        }

        $this->verify_csrf();
        $pages = $this->model('modules_model')->get_all();
        $host = URLROOT;

        $excluded = ['admin.php','auth.php','health.php','sentinel.php','modules.php','ror.php','llms.php','sitemap.php','error_handler.php', 'media.php', 'accounts.php', 'traffic.php'];

        /* Force lowercase */
        $files = array_map('strtolower', scandir(APPROOT . '/controllers'));
        $controllers = array_diff($files, array_merge(['.','..'],$excluded));

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($controllers as $file)
        {
            $name = str_replace('.php','',$file);
            $url = ($name === 'home') ? $host : $host . '/' . rawurlencode($name);
            $url = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');

            $xml .= "  <url>" . PHP_EOL;
            $xml .= "    <loc>$url</loc>" . PHP_EOL;
            $xml .= "  </url>" . PHP_EOL;
        }

        if (!empty($pages))
        {
            foreach ($pages as $p)
            {
                $xml .= "  <url>" . PHP_EOL;
                $url = htmlspecialchars($host . '/' . rawurlencode($p['slug']), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "    <loc>$url</loc>" . PHP_EOL;
                $xml .= "  </url>" . PHP_EOL;
            }
        }

        $xml .= '</urlset>';

        file_put_contents(PUBROOT.'/sitemap.xml',$xml);

        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
