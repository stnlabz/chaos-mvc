<?php

/**
 * Chaos MVC Installer
 *
 * Handles initial database configuration, schema installation,
 * administrator account creation, and installation locking.
 *
 * Path: /app/controllers/install.php
 *
 * @package Chaos MVC
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 02:19:00 UTC] */
class install extends controller
{
    /**
     * Run the Chaos MVC installer.
     *
     * Displays the installer form for GET requests and processes
     * installation data for POST requests.
     *
     * @return void
     */
    public function index()
    {
        $lockFile = LOG_PATH . '/install.lock';

        if (file_exists($lockFile)) {
            header('Location: /login');
            exit;
        }

        /*
         * CMSEC-2026-4831-A — Completed-installation boundary
         *
         * The lock file is the primary installation marker. If that runtime
         * file is removed while the installed database remains available,
         * the previous lock-only behavior exposed the installer again.
         * Confirm a completed administrator installation before rendering or
         * processing the installer, without changing database state.
         */
        try {
            $installedDatabase = @new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );

            if (!$installedDatabase->connect_errno) {
                $result = $installedDatabase->query(
                    'SELECT 1 FROM accounts WHERE user_level = 9 LIMIT 1'
                );
                $installationComplete = $result instanceof mysqli_result
                    && $result->num_rows > 0;

                if ($result instanceof mysqli_result) {
                    $result->free();
                }

                $installedDatabase->close();

                if ($installationComplete) {
                    header('Location: /login');
                    exit;
                }
            } else {
                $installedDatabase->close();
            }
        } catch (Throwable $error) {
            /*
             * A missing database, schema, or valid administrator is expected
             * during a genuine first installation. Continue to the installer.
             */
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->view('public/install/index');
            return;
        }

        $this->verify_csrf();

        $host = trim($_POST['db_host'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $name = trim($_POST['db_name'] ?? '');

        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminDisplayName = trim($_POST['admin_display_name'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';

        if (
            $host === '' ||
            $user === '' ||
            $name === '' ||
            $adminUser === '' ||
            $adminEmail === '' ||
            $adminDisplayName === '' ||
            $adminPass === ''
        ) {
            $this->view(
                'public/install/index',
                ['error' => 'All required fields must be completed.']
            );
            return;
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->view(
                'public/install/index',
                ['error' => 'A valid administrator email address is required.']
            );
            return;
        }

        if (strlen($adminPass) < 12) {
            $this->view('public/install/index', [
                'error' => 'The administrator password must contain at least 12 characters.'
            ]);
            return;
        }

        /*
         * Test DB Connection
         */
        $mysqli = @new mysqli($host, $user, $pass, $name);

        if ($mysqli->connect_errno) {
            $this->view(
                'public/install/index',
                ['error' => 'Database connection failed.']
            );
            return;
        }

        $mysqli->set_charset('utf8mb4');

        /*
         * Run SQL Schema
         */
        $schemaFile = APPROOT . '/install/schema.sql';
        $schema = file_get_contents($schemaFile);

        if ($schema === false || trim($schema) === '') {
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Installer schema could not be loaded.']
            );
            return;
        }

        if (!$mysqli->multi_query($schema)) {
            $error = $mysqli->error;
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Database schema installation failed: ' . $error]
            );
            return;
        }

        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }

            if (!$mysqli->more_results()) {
                break;
            }

            if (!$mysqli->next_result()) {
                $error = $mysqli->error;
                $mysqli->close();

                $this->view(
                    'public/install/index',
                    ['error' => 'Database schema installation failed: ' . $error]
                );
                return;
            }
        } while (true);

        /*
         * Create Administrator Account
         */
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare(
            'INSERT INTO accounts
                (
                    username,
                    email_address,
                    password_hash,
                    role,
                    user_level,
                    display_name
                )
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            $error = $mysqli->error;
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Administrator account preparation failed: ' . $error]
            );
            return;
        }

        $role = 'admin';
        $userLevel = 9;

        $stmt->bind_param(
            'ssssis',
            $adminUser,
            $adminEmail,
            $hash,
            $role,
            $userLevel,
            $adminDisplayName
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;

            $stmt->close();
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Administrator account creation failed: ' . $error]
            );
            return;
        }

        $stmt->close();

        /*
         * Write config.php
         */
        $config = "<?php\n\n";
        $config .= "define('DB_HOST', " . var_export($host, true) . ");\n";
        $config .= "define('DB_USER', " . var_export($user, true) . ");\n";
        $config .= "define('DB_PASS', " . var_export($pass, true) . ");\n";
        $config .= "define('DB_NAME', " . var_export($name, true) . ");\n";

        $configFile = APPROOT . '/core/config.php';

        if (file_put_contents($configFile, $config, LOCK_EX) === false) {
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Database configuration could not be written.']
            );
            return;
        }

        /*
         * Lock Installer
         */
        if (file_put_contents($lockFile, 'installed', LOCK_EX) === false) {
            $mysqli->close();

            $this->view(
                'public/install/index',
                ['error' => 'Installation completed, but the installer lock could not be written.']
            );
            return;
        }

        $mysqli->close();

        header('Location: /login');
        exit;
    }
}
/* [End AI:GPT-5.6 Sol] */
