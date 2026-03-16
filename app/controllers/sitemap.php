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
class sitemap extends controller
{
    public static $is_core = true;

    public function index()
    {
        $pages = $this->model('modules_model')->get_all();
        $host = "https://" . $_SERVER['HTTP_HOST'];

        $excluded = ['admin.php','auth.php','health.php','sentinel.php','modules.php','ror.php','llms.php','sitemap.php','error_handler.php'];

        /* Force lowercase */
        $files = array_map('strtolower', scandir(APPROOT . '/controllers'));
        $controllers = array_diff($files, array_merge(['.','..'],$excluded));

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($controllers as $file)
        {
            $name = str_replace('.php','',$file);
            $url = ($name === 'home') ? $host : "$host/$name";

            $xml .= "  <url>" . PHP_EOL;
            $xml .= "    <loc>$url</loc>" . PHP_EOL;
            $xml .= "  </url>" . PHP_EOL;
        }

        if (!empty($pages))
        {
            foreach ($pages as $p)
            {
                $xml .= "  <url>" . PHP_EOL;
                $xml .= "    <loc>$host/{$p['slug']}</loc>" . PHP_EOL;
                $xml .= "  </url>" . PHP_EOL;
            }
        }

        $xml .= '</urlset>';

        file_put_contents(PUBROOT.'/sitemap.xml',$xml);

        return true;
    }
}
