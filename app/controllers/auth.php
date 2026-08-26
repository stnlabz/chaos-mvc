<?php

/**
 * Auth Controller
 *
 * Handles authentication, registration, account recovery, and
 * administrative account deletion.
 *
 * Security Maintenance
 *
 * CMSEC-2026-4827 — Authentication Boundary Hardening
 *
 * Findings:
 * - A: State-changing authentication operations require CSRF verification.
 * - B: Logout and account deletion must be POST-only.
 * - C: Password-reset bearer tokens must not be stored in plaintext.
 * - D: Registration and password reset require a consistent minimum password.
 * - E: Login and password recovery require bounded request throttling.
 * - F: Authentication action dispatch must use an explicit action allowlist.
 * - G: Successful authentication must invalidate the previous session ID.
 *
 * Status:
 * Protected-owner remediation applied. Qualification and regression testing
 * required before release.
 *
 * CMSEC identifiers are internal Chaos MVC security tracking identifiers.
 * They are not CVE identifiers.
 *
 * Path: /app/controllers/auth.php
 * LOCKED CORE FILE
 */

/** [AI:GPT-5.6 Sol | 2026-08-26 14:20:00 UTC]
    [HUMAN:Mei | 2026-08-26 15:56 UTC] 
*/

class auth extends controller
{
    /**
     * Minimum accepted password length.
     */
    private const MIN_PASSWORD_LENGTH = 12;

    /**
     * Explicitly routable authentication actions.
     */
    private const AUTH_ACTIONS = [
        'login',
        'logout',
        'register',
        'forgot_password',
        'reset_password',
        'delete',
    ];

    /**
     * Router entry.
     *
     * CMSEC-2026-4827-F
     *
     * Dispatch only explicitly approved authentication actions.
     *
     * @param array $url Route segments.
     * @return void
     */
    public function index($url = []): void
    {
        $method = (string) ($url[1] ?? 'login');

        if (
            in_array(
                $method,
                self::AUTH_ACTIONS,
                true
            )
            && method_exists(
                $this,
                $method
            )
        ) {
            $this->$method();
            return;
        }

        $this->login();
    }

    /**
     * Login handler.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-E
     * CMSEC-2026-4827-G
     *
     * @return void
     */
    public function login(): void
    {
        $data = [];

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            === 'POST'
        ) {
            $this->require_csrf();

            $username = trim(
                (string) (
                    $_POST['username']
                    ?? ''
                )
            );

            $password = (string) (
                $_POST['password']
                ?? ''
            );

            $ip = $this->request_ip();
            $throttle = $this->auth_throttle();

            if (
                !$throttle->is_login_allowed(
                    $username,
                    $ip
                )
            ) {
                /*
                 * Do not reveal throttle state to the requester.
                 */
                $data['error'] =
                    'Invalid credentials.';

                $this->view(
                    'auth/login',
                    $data
                );

                return;
            }

            $model = $this->model(
                'accounts_model'
            );

            $user = $model->authenticate(
                $username,
                $password
            );

            if ($user) {
                /*
                 * CMSEC-2026-4827-G
                 *
                 * Replace the authenticated session identifier and remove
                 * the previous session state.
                 */
                session_regenerate_id(true);

                unset(
                    $_SESSION['csrf_token']
                );

                $throttle->clear_login_failures(
                    $username,
                    $ip
                );

                $_SESSION['user_id'] =
                    $user['id'];

                $_SESSION['username'] =
                    $user['username'];

                $_SESSION['user_level'] =
                    $user['user_level'];

                $_SESSION['role'] =
                    $user['role'];

                if (
                    (int) $user['user_level']
                    === 9
                ) {
                    header(
                        'Location: /admin'
                    );

                    exit;
                }

                header(
                    'Location: /'
                );

                exit;
            }

            $throttle->record_login_failure(
                $username,
                $ip
            );

            $data['error'] =
                'Invalid credentials.';
        }

        $this->view(
            'auth/login',
            $data
        );
    }

    /**
     * Logout handler.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-B
     *
     * @return void
     */
    public function logout(): void
    {
        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            !== 'POST'
        ) {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }

        $this->require_csrf();

        if (
            session_status()
            === PHP_SESSION_NONE
        ) {
            session_start();
        }

        $_SESSION = [];

        if (
            ini_get(
                'session.use_cookies'
            )
        ) {
            $params =
                session_get_cookie_params();

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

        header(
            'Location: /login'
        );

        exit;
    }

    /**
     * Signup / Registration handler.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-D
     *
     * @return void
     */
    public function register(): void
    {
        $data = [];

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            === 'POST'
        ) {
            $this->require_csrf();

            $model = $this->model(
                'accounts_model'
            );

            $payload = [
                'username' => trim(
                    (string) (
                        $_POST['username']
                        ?? ''
                    )
                ),
                'email_address' => trim(
                    (string) (
                        $_POST['email_address']
                        ?? ''
                    )
                ),
                'display_name' => trim(
                    (string) (
                        $_POST['display_name']
                        ?? ''
                    )
                ),
                'password' => (string) (
                    $_POST['password']
                    ?? ''
                ),
                'user_level' => 1,
            ];

            if (
                $payload['username'] === ''
                || $payload['email_address'] === ''
                || $payload['password'] === ''
            ) {
                $data['error'] =
                    'All required fields must be completed.';
            } elseif (
                !filter_var(
                    $payload['email_address'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $data['error'] =
                    'A valid email address is required.';
            } elseif (
                !preg_match(
                    '/^[a-z0-9_.-]{3,50}$/i',
                    $payload['username']
                )
            ) {
                $data['error'] =
                    'Username must be 3-50 letters, numbers, dots, dashes, or underscores.';
            } elseif (
                strlen(
                    $payload['password']
                )
                < self::MIN_PASSWORD_LENGTH
            ) {
                $data['error'] =
                    'Password must be at least '
                    . self::MIN_PASSWORD_LENGTH
                    . ' characters.';
            } else {
                $result = $model->create(
                    $payload
                );

                if ($result) {
                    header(
                        'Location: /login?signup=success'
                    );

                    exit;
                }

                $data['error'] =
                    'Signup failed. Username or email may already be in use.';
            }
        }

        $this->view(
            'auth/register',
            $data
        );
    }

    /**
     * Forgot password handler.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-C
     * CMSEC-2026-4827-E
     *
     * @return void
     */
    public function forgot_password(): void
    {
        $data = [];

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            === 'POST'
        ) {
            $this->require_csrf();

            $email = strtolower(
                trim(
                    (string) (
                        $_POST['email']
                        ?? ''
                    )
                )
            );

            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $data['error'] =
                    'Invalid email address.';

                $this->view(
                    'auth/forgot_password',
                    $data
                );

                return;
            }

            $ip = $this->request_ip();
            $throttle = $this->auth_throttle();

            /*
             * CMSEC-2026-4827-E
             *
             * Recovery requests always return the same external response.
             * When throttled, no account lookup or mail delivery occurs.
             */
            if (
                !$throttle->is_recovery_allowed(
                    $email,
                    $ip
                )
            ) {
                $data['success'] =
                    'If that account exists, a recovery link has been sent.';

                $this->view(
                    'auth/forgot_password',
                    $data
                );

                return;
            }

            /*
             * Record the request regardless of whether the account exists
             * so throttle behavior cannot be used for account enumeration.
             */
            $throttle->record_recovery_request(
                $email,
                $ip
            );

            $model = $this->model(
                'accounts_model'
            );

            $user = $model->fetch(
                'SELECT id FROM accounts WHERE email_address = ? LIMIT 1',
                [$email]
            );

            if ($user) {
                /*
                 * CMSEC-2026-4827-C
                 *
                 * The raw bearer token is sent to the user.
                 * Only its SHA-256 digest is persisted.
                 */
                $token = bin2hex(
                    random_bytes(32)
                );

                $tokenHash = hash(
                    'sha256',
                    $token
                );

                $expires = date(
                    'Y-m-d H:i:s',
                    strtotime('+1 hour')
                );

                /*
                 * Invalidate earlier outstanding recovery records for the
                 * same account before issuing a new recovery token.
                 */
                $model->query(
                    'DELETE FROM password_resets WHERE email = ?',
                    [$email]
                );

                $model->query(
                    'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
                    [
                        $email,
                        $tokenHash,
                        $expires,
                    ]
                );

                $resetLink =
                    URLROOT
                    . '/reset-password/'
                    . $token;

                require_once
                    APPROOT
                    . '/lib/mailer.php';

                $mailerObj =
                    new mailer();

                $mail =
                    $mailerObj->create();

                try {
                    $mail->addAddress(
                        $email
                    );

                    $mail->Subject =
                        'Account Recovery';

                    $safeLink =
                        htmlspecialchars(
                            $resetLink,
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    $mail->Body =
                        "Reset Link: <a href=\"{$safeLink}\">{$safeLink}</a>";

                    $mail->send();
                } catch (Exception $e) {
                    error_log(
                        'Mailer Error: '
                        . $mail->ErrorInfo
                    );
                }
            }

            $data['success'] =
                'If that account exists, a recovery link has been sent.';
        }

        $this->view(
            'auth/forgot_password',
            $data
        );
    }

    /**
     * Reset password handler.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-C
     * CMSEC-2026-4827-D
     *
     * @param array|string $params Route parameters.
     * @return void
     */
    public function reset_password(
        $params = []
    ): void {
        $token = is_array($params)
            ? (string) (
                $params[0]
                ?? ''
            )
            : (string) $params;

        /*
         * random_bytes(32) encoded with bin2hex produces exactly
         * 64 hexadecimal characters.
         */
        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $token
            )
        ) {
            http_response_code(404);

            $this->error_page(
                'Invalid or expired token.'
            );
        }

        $tokenHash = hash(
            'sha256',
            $token
        );

        $model = $this->model(
            'accounts_model'
        );

        $reset = $model->fetch(
            'SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1',
            [$tokenHash]
        );

        if (!$reset) {
            http_response_code(404);

            $this->error_page(
                'Invalid or expired token.'
            );
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            === 'POST'
        ) {
            $this->require_csrf();

            $password = (string) (
                $_POST['password']
                ?? ''
            );

            if (
                strlen($password)
                < self::MIN_PASSWORD_LENGTH
            ) {
                $this->view(
                    'auth/reset_password',
                    [
                        'token' => $token,
                        'error' =>
                            'Password must be at least '
                            . self::MIN_PASSWORD_LENGTH
                            . ' characters.',
                    ]
                );

                return;
            }

            /*
             * CMSEC-2026-4827-C
             *
             * Consume the exact recovery token before applying the password
             * change so the bearer credential cannot be reused.
             */
            $consume = $model->query(
                'DELETE FROM password_resets WHERE token = ? AND expires_at > NOW()',
                [$tokenHash]
            );

            if (
                $consume->rowCount()
                !== 1
            ) {
                http_response_code(409);

                $this->error_page(
                    'Invalid or expired token.'
                );
            }

            $newPassword =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

            $model->query(
                'UPDATE accounts SET password_hash = ? WHERE email_address = ?',
                [
                    $newPassword,
                    $reset['email'],
                ]
            );

            /*
             * Invalidate any other recovery records belonging to the
             * account after the password has changed.
             */
            $model->query(
                'DELETE FROM password_resets WHERE email = ?',
                [$reset['email']]
            );

            header(
                'Location: /login?reset=success'
            );

            exit;
        }

        $this->view(
            'auth/reset_password',
            [
                'token' => $token,
            ]
        );
    }

    /**
     * Delete account.
     *
     * CMSEC-2026-4827-A
     * CMSEC-2026-4827-B
     *
     * Deletes the requested account while preventing the currently
     * authenticated administrator from deleting their own account.
     *
     * @param array|string|int|null $params Route parameters.
     * @return void
     */
    public function delete(
        $params = null
    ): void {
        if (
            !isset(
                $_SESSION['user_id']
            )
            || (int) (
                $_SESSION['user_level']
                ?? 0
            ) !== 9
        ) {
            header(
                'Location: /login'
            );

            exit;
        }

        if (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            !== 'POST'
        ) {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }

        $this->require_csrf();

        $id = is_array($params)
            ? (
                $params[0]
                ?? null
            )
            : $params;

        if (
            $id === null
            || $id === ''
            || filter_var(
                $id,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ]
            ) === false
        ) {
            $_SESSION['msg'] =
                'Invalid account ID.';

            $_SESSION['msg_type'] =
                'danger';

            header(
                'Location: /admin/accounts'
            );

            exit;
        }

        $id = (int) $id;

        if (
            $id
            === (int) $_SESSION['user_id']
        ) {
            $_SESSION['msg'] =
                'You cannot delete your own account.';

            $_SESSION['msg_type'] =
                'danger';

            header(
                'Location: /admin/accounts'
            );

            exit;
        }

        $model = $this->model(
            'accounts_model'
        );

        if (
            $model->delete($id)
        ) {
            $_SESSION['msg'] =
                "Account #{$id} deleted.";

            $_SESSION['msg_type'] =
                'success';
        } else {
            $_SESSION['msg'] =
                'Deletion failed.';

            $_SESSION['msg_type'] =
                'danger';
        }

        header(
            'Location: /admin/accounts'
        );

        exit;
    }

    /**
     * Load the authentication throttle service.
     *
     * CMSEC-2026-4827-E
     *
     * @return auth_throttle
     */
    private function auth_throttle(): auth_throttle
    {
        require_once
            APPROOT
            . '/lib/auth_throttle.php';

        return new auth_throttle();
    }

    /**
     * Return the direct request source address.
     *
     * The application does not trust forwarding headers here because
     * proxy trust has not been established by this controller.
     *
     * CMSEC-2026-4827-E
     *
     * @return string
     */
    private function request_ip(): string
    {
        $ip = trim(
            (string) (
                $_SERVER['REMOTE_ADDR']
                ?? ''
            )
        );

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            return '0.0.0.0';
        }

        return $ip;
    }
}

/* [End AI:GPT-5.6 Sol] */