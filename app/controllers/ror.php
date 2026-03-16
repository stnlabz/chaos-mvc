<?php
/**
 * ROR Controller
 * Generates ror.xml using controller + module discovery.
 * Follows the same peek → look → produce → update pattern as llms.
 */
 
 /**
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */
 
class ror extends controller
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
        $xml .= '<rss version="2.0">' . PHP_EOL;
        $xml .= '  <channel>' . PHP_EOL;
        $xml .= '    <title>Poe Mei</title>' . PHP_EOL;
        $xml .= '    <link>'.$host.'</link>' . PHP_EOL;
        $xml .= '    <description>Poe Mei Resource Feed</description>' . PHP_EOL;

        foreach ($controllers as $file)
        {
            $name = str_replace('.php','',$file);
            $url = ($name === 'home') ? $host : "$host/$name";

            $xml .= "    <item>" . PHP_EOL;
            $xml .= "      <title>$name</title>" . PHP_EOL;
            $xml .= "      <link>$url</link>" . PHP_EOL;
            $xml .= "    </item>" . PHP_EOL;
        }

        if (!empty($pages))
        {
            foreach ($pages as $p)
            {
                $xml .= "    <item>" . PHP_EOL;
                $xml .= "      <title>{$p['title']}</title>" . PHP_EOL;
                $xml .= "      <link>$host/{$p['slug']}</link>" . PHP_EOL;
                $xml .= "    </item>" . PHP_EOL;
            }
        }

        $xml .= '  </channel>' . PHP_EOL;
        $xml .= '</rss>';

        file_put_contents(PUBROOT.'/ror.xml',$xml);

        return true;
    }
}
