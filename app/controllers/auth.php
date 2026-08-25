<?php

/**
 * Auth Controller
 *
 * Handles authentication, registration, and account recovery.
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class auth extends controller
{
    /**
     * Router entry
     *
     * @param array $url
     * @return void
     */
    public function index($url = [])
    {
        $method = $url[1] ?? 'login';

        if (method_exists($this, $method)) {
            $this->$method();
            return;
        }

        $this->login();
    }

    /**
     * Login handler
     *
     * @return void
     */
    public function login()
    {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verify_csrf();
            $model = $this->model('accounts_model');

            $user = $model->authenticate(
                $_POST['username'] ?? '',
                $_POST['password'] ?? ''
            );

            if ($user) {
                session_regenerate_id(true);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['user_level'] = $user['user_level'];
                $_SESSION['role']      = $user['role'];

                if ((int)$user['user_level'] === 9) {
                    header('Location: /admin');
                    exit;
                }

                header('Location: /');
                exit;
            }

            $data['error'] = 'Invalid credentials.';
        }

        $this->view('auth/login', $data);
    }

    /**
     * Logout handler
     *
     * @return void
     */
    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /');
            exit;
        }

        $this->verify_csrf();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: /login');
        exit;
    }

    /**
     * Signup / Registration handler
     *
     * @return void
     */
    public function register()
    {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verify_csrf();
            $model = $this->model('accounts_model');

            $payload = [
                'username'      => trim($_POST['username'] ?? ''),
                'email_address' => trim($_POST['email_address'] ?? ''),
                'display_name'  => trim($_POST['display_name'] ?? ''),
                'password'      => $_POST['password'] ?? '',
                'user_level'    => 1
            ];

            if (
                empty($payload['username']) ||
                empty($payload['email_address']) ||
                empty($payload['password']) ||
                !filter_var($payload['email_address'], FILTER_VALIDATE_EMAIL) ||
                strlen($payload['password']) < 12
            ) {
                $data['error'] = 'Complete all fields with a valid email and a 12-character password.';
            } else {
                try {
                    $result = $model->create($payload);
                } catch (PDOException $e) {
                    $result = false;
                }

                if ($result) {
                    header('Location: /login?signup=success');
                    exit;
                }

                $data['error'] = 'Signup failed. Username or email may already be in use.';
            }
        }

        $this->view('auth/register', $data);
    }

    /**
     * Forgot password handler
     *
     * @return void
     */
    public function forgot_password()
    {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verify_csrf();
            $email = trim($_POST['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $data['error'] = 'Invalid email address.';
                $this->view('auth/forgot_password', $data);
                return;
            }

            $model = $this->model('accounts_model');

            $user = $model->fetch(
                "SELECT id FROM accounts WHERE email_address = ? LIMIT 1",
                [$email]
            );

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $model->query(
                    "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
                    [$email, $tokenHash, $expires]
                );

                $resetLink = URLROOT . '/reset-password/' . $token;

                require_once APPROOT . '/lib/mailer.php';

                $mailerObj = new mailer();
                $mail = $mailerObj->create();

                try {
                    $mail->addAddress($email);
                    $mail->Subject = 'Account Recovery';
                    $mail->Body = "Reset Link: <a href='{$resetLink}'>{$resetLink}</a>";
                    $mail->send();
                } catch (Exception $e) {
                    error_log('Mailer Error: ' . $mail->ErrorInfo);
                }
            }

            $data['success'] = 'If that account exists, a recovery link has been sent.';
        }

        $this->view('auth/forgot_password', $data);
    }

    /**
     * Reset password handler
     *
     * @param array $params
     * @return void
     */
    public function reset_password($params = [])
    {
        $token = $params[0] ?? '';

        $model = $this->model('accounts_model');

        $reset = $model->fetch(
            "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1",
            [hash('sha256', $token)]
        );

        if (!$reset) {
            die('Invalid or expired token.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verify_csrf();
            $password = $_POST['password'] ?? '';

            if (strlen($password) < 12) {
                $this->view(
                    'auth/reset_password',
                    ['token' => $token, 'error' => 'Password must contain at least 12 characters.']
                );
                return;
            }

            $newPassword = password_hash($password, PASSWORD_DEFAULT);

            $model->query(
                "UPDATE accounts SET password_hash = ? WHERE email_address = ?",
                [$newPassword, $reset['email']]
            );

            $model->query(
                "DELETE FROM password_resets WHERE email = ?",
                [$reset['email']]
            );

            header('Location: /login?reset=success');
            exit;
        }

        $this->view('auth/reset_password', ['token' => $token]);
    }

    /**
 * Delete account.
 *
 * Deletes the requested account while preventing the currently
 * authenticated administrator from deleting their own account.
 *
 * @param array|string|int|null $params Route parameters.
 * @return void
 */
public function delete($params = null): void
{
    if (
        !isset($_SESSION['user_id']) ||
        (int) ($_SESSION['user_level'] ?? 0) !== 9
    ) {
        header('Location: /login');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /admin/accounts');
        exit;
    }

    $this->verify_csrf();

    $id = is_array($params)
        ? ($params[0] ?? null)
        : $params;

    if (!$id) {
        $_SESSION['msg'] = 'Invalid account ID.';
        $_SESSION['msg_type'] = 'danger';

        header('Location: /admin/accounts');
        exit;
    }

    $id = (int) $id;

    if ($id === (int) $_SESSION['user_id']) {
        $_SESSION['msg'] = 'You cannot delete your own account.';
        $_SESSION['msg_type'] = 'danger';

        header('Location: /admin/accounts');
        exit;
    }

    $model = $this->model('accounts_model');

    if ($model->delete($id)) {
        $_SESSION['msg'] = "Account #{$id} deleted.";
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['msg'] = 'Deletion failed.';
        $_SESSION['msg_type'] = 'danger';
    }

    header('Location: /admin/accounts');
    exit;
}
}
/* [End AI:GPT-5.6 Sol] */
