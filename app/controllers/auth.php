<?php
// path: /app/controllers/auth.php

class auth extends controller {

    public function index($url = []) {
        $method = $url[1] ?? 'login';
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $this->login();
        }
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $model = $this->model('accounts_model');
            // Authenticating via USERNAME - No assumptions.
            $user = $model->authenticate($_POST['username'], $_POST['password']);

            if ($user) {
                session_regenerate_id();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_level'] = $user['user_level']; // RESTORED: This fixed the admin lockout
                $_SESSION['role'] = $user['role'];
                
                if ($user['user_level'] == 9) {
                    header("Location: /admin");
                    exit;
                }
                else {
                    header("Location: /");
                }
            } else {
                $data['error'] = "Invalid Credentials.";
            }
        }
        $this->view('auth/login', $data ?? []);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: /login");
        exit;
    }

    public function forgot_password() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $model = $this->model('accounts_model');
            
            $user = $model->fetch("SELECT id FROM accounts WHERE email_address = :email", ['email' => $email]);
            
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

                $model->query("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires)", [
                    'email' => $email,
                    'token' => $token,
                    'expires' => $expires
                ]);

                $resetLink = "https://poemei.com/reset-password/" . $token;
                
                require_once '../app/lib/mailer.php';
                $mailerObj = new mailer();
                $mail = $mailerObj->create();

                try {
                    $mail->addAddress($email);
                    $mail->Subject = "Account Recovery";
                    $mail->Body = "Reset Link: <a href='$resetLink'>$resetLink</a>";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                }
            }
            $data['success'] = "If that account exists, a recovery link has been sent.";
        }
        $this->view('auth/forgot_password', $data ?? []);
    }

    public function reset_password($params = []) {
        $token = $params[0] ?? '';
        $model = $this->model('accounts_model');
        
        $reset = $model->fetch("SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW()", ['token' => $token]);

        if (!$reset) {
            die("Invalid or expired token.");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            
            $model->query("UPDATE accounts SET password_hash = :pass WHERE email_address = :email", [
                'pass' => $new_password,
                'email' => $reset['email']
            ]);

            $model->query("DELETE FROM password_resets WHERE email = :email", ['email' => $reset['email']]);

            header("Location: /login?reset=success");
            exit;
        }

        $this->view('auth/reset_password', ['token' => $token]);
    }
    
    /**
     * Signup Method
     * Fixed to match register.php form and accounts_model.php
     */
    public function signup() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $model = $this->model('accounts_model');
        
        // 1. Get data from the form
        $username = trim($_POST['username']);
        $email = trim($_POST['email_address']); 
        $display_name = trim($_POST['display_name']);
        $password = $_POST['password'];
        $level = 1; // Default level for new signups

        // 2. Call the existing model method
        // The model expects: create($username, $password, $name, $level)
        try {
            // Note: We need to update the model to handle email_address as well
            $model->create($username, $password, $display_name, $level, $email);
            header("Location: /login?signup=success");
            exit;
        } catch (Exception $e) {
            $data['error'] = "Signup failed. Username or email may already be in use.";
        }
    }
    $this->view('auth/register', $data ?? []);
}

    public function delete($id = null) {
    // 1. Authenticate as Admin
    if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 9) {
        header("Location: /login");
        exit;
    }

    // 2. CRITICAL SAFETY: Prevent self-deletion
    // We use 'user_id' because that is what auth.php sets upon login
    if ((int)$id === (int)$_SESSION['user_id']) {
        $_SESSION['msg'] = "Error: You cannot delete the account you are currently logged into.";
        $_SESSION['msg_type'] = "danger";
        header("Location: /admin/accounts");
        exit;
    }

    if ($id) {
        $model = $this->model('accounts_model');
        
        // 3. Forced Integer Casting
        // This ensures an associative array or malicious string cannot be passed
        if ($model->delete((int)$id)) {
            $_SESSION['msg'] = "Account #$id deleted.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "Deletion failed.";
            $_SESSION['msg_type'] = "danger";
        }
    }

    header("Location: /admin/accounts");
    exit;
}
}
