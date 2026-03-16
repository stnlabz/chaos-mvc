<?php
/**
 * Chaos MVC Installer
 * path: /app/controllers/install.php
 */

class install extends controller
{

    public function index()
    {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $host = trim($_POST['db_host']);
            $user = trim($_POST['db_user']);
            $pass = trim($_POST['db_pass']);
            $name = trim($_POST['db_name']);

            $adminUser = trim($_POST['admin_user']);
            $adminEmail = trim($_POST['admin_email']);
            $adminPass = $_POST['admin_pass'];

            if (!$host || !$user || !$name || !$adminUser || !$adminEmail || !$adminPass) {
                $this->view('public/install/index', ['error' => 'All fields required']);
                return;
            }

            /* ------------------------------
               Test DB Connection
            ------------------------------ */

            $mysqli = @new mysqli($host, $user, $pass, $name);

            if ($mysqli->connect_errno) {
                $this->view('public/install/index', ['error' => 'Database connection failed']);
                return;
            }

            /* ------------------------------
               Write config.php
            ------------------------------ */

            $config = "<?php\n";
            $config .= "define('DB_HOST', '{$host}');\n";
            $config .= "define('DB_USER', '{$user}');\n";
            $config .= "define('DB_PASS', '{$pass}');\n";
            $config .= "define('DB_NAME', '{$name}');\n";

            file_put_contents(APPROOT . '/core/config.php', $config);

            /* ------------------------------
               Run SQL Schema
            ------------------------------ */

            $schema = file_get_contents(APPROOT . '/install/schema.sql');

            if ($schema) {

                $mysqli->multi_query($schema);

                while ($mysqli->more_results() && $mysqli->next_result()) {}

            }

            /* ------------------------------
               Create Admin
            ------------------------------ */

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);

            $stmt = $mysqli->prepare("
                INSERT INTO users (username, email, password, role, created_at)
                VALUES (?, ?, ?, 'admin', NOW())
            ");

            $stmt->bind_param("sss", $adminUser, $adminEmail, $hash);
            $stmt->execute();

            /* ------------------------------
               Lock Installer
            ------------------------------ */

            file_put_contents(LOG_PATH . '/install.lock', 'installed');

            header("Location: /login");
            exit;
        }

        $this->view('public/install/index');
    }
}
