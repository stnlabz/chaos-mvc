<?php require_once APPROOT . '/views/inc/head.php';

require_once APPROOT . '/lib/render_md.php';
$render = new render_md();
$text = "
# Quick Start
Typical installation steps from **GitHub**:

 - Clone the repository @ `git clone https://github.com/stnlabz/chaos-mvc.git`
 - Configure database credentials in `/app/core/config.php`
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'database');
```
 - Import the database schema `/app/install/schema.sql` via `phpmyadmin` or `MySQL` shell.
 - Setup first user with `password_hash()` password_hash & set `user_level` to 9
 - Log in
";
echo $render->markdown($text);
require_once APPROOT . '/views/inc/foot.php';
