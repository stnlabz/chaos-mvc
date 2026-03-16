<?php require_once APPROOT . '/views/inc/head.php';

require_once APPROOT . '/lib/render_md.php';
$render = new render_md();
$text = "
# Downloads
Downloading the Chaos MVC Source:

1. Grab this file (**Download Not Available**)
2. Configure database credentials in `/app/core/config.php`
3. Import the database schema
4. After installation, the framework is ready to run.
";
echo $render->markdown($text);
require_once APPROOT . '/views/inc/foot.php';
