<?php

/**
 * Chaos MVC Base Controller
 *
 * Provides shared controller services and resolves Core-owned and
 * user-land models and views according to controller ownership.
 *
 * Path: /app/core/controller.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-26 UTC] */
class controller
{
    /**
     * Markdown renderer.
     *
     * @var render_md
     */
    protected $render_md;

    /**
     * Optional trash filter.
     *
     * @var trash_filter|null
     */
    protected $trash_filter;

    /**
     * User module slug when operating in user-land.
     *
     * Null identifies a Core controller.
     *
     * @var string|null
     */
    protected $module_context = null;

    /**
     * Core Modules (System Protected).
     */
    protected const CORE_MODULES = [
        'traffic',
        'posts',
        'media',
        'accounts',
        'health',
        'modules',
        'site',
        'updater',
    ];

    /**
     * Initialize shared controller services.
     */
    public function __construct()
    {
        $this->render_md = new render_md();

        if (file_exists(APPROOT . '/lib/trash_filter.php')) {
            require_once APPROOT . '/lib/trash_filter.php';
            $this->trash_filter = new trash_filter();
        }
    }

    /**
     * Assign user-module ownership context.
     *
     * This is set by Core after resolving a controller from
     * /user/modules/{slug}.
     *
     * @param string $module Module slug.
     *
     * @return void
     */
    public function setModuleContext(string $module): void
    {
        if (
            !preg_match(
                '/^[a-z][a-z0-9_]{0,62}$/',
                $module
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid user module context.'
            );
        }

        $moduleRoot = USERROOT
            . '/modules/'
            . $module;

        $modulesRoot = realpath(USERROOT . '/modules');
        $resolvedModuleRoot = realpath($moduleRoot);

        if (
            $modulesRoot === false
            || $resolvedModuleRoot === false
            || is_link($moduleRoot)
            || !str_starts_with(
                $resolvedModuleRoot,
                $modulesRoot . DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException(
                'User module context does not exist.'
            );
        }

        $this->module_context = $module;
    }

    /**
     * Return whether this controller is executing from user-land.
     *
     * @return bool
     */
    public function isUserModule(): bool
    {
        return $this->module_context !== null;
    }

    /**
     * Return the current user module slug.
     *
     * @return string|null
     */
    public function getModuleContext(): ?string
    {
        return $this->module_context;
    }

    /**
     * Return a CSRF token.
     *
     * @return string
     */
    public function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return (string) $_SESSION['csrf_token'];
    }

    /**
     * Verify the submitted CSRF token.
     *
     * @return void
     */
    protected function verify_csrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';

        if (
            !is_string($token)
            || !hash_equals(
                $this->csrf_token(),
                $token
            )
        ) {
            http_response_code(403);

            $this->error_page(
                'The request could not be verified. Please try again.'
            );
        }
    }

    /**
     * Require valid CSRF verification.
     *
     * @return void
     */
    protected function require_csrf(): void
    {
        $this->verify_csrf();
    }

    /**
     * Return a CSRF hidden field.
     *
     * @return string
     */
    public function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(
                $this->csrf_token(),
                ENT_QUOTES,
                'UTF-8'
            )
            . '">';
    }

    /**
     * Require an administrative account level.
     *
     * @param int $level Required user level.
     *
     * @return void
     */
    protected function require_admin(int $level = 7): void
    {
        if (
            (int) ($_SESSION['user_level'] ?? 0)
            < $level
        ) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Determine whether a module belongs to Core.
     *
     * @param string $module Module slug.
     *
     * @return bool
     */
    protected function isCore(string $module): bool
    {
        return in_array(
            $module,
            self::CORE_MODULES,
            true
        );
    }

    /**
     * Render a view.
     *
     * Core controllers resolve views from /app/views.
     *
     * User module controllers resolve views from:
     *
     * /user/modules/{module}/views
     *
     * @param string|array $view View identifier.
     * @param array        $data View data.
     *
     * @return void
     */
    public function view($view, $data = []): void
    {
        if (is_array($view)) {
            $view = reset($view);
        }

        $view = (string) $view;

        if (!$this->validRelativePath($view)) {
            $this->error_page(
                'The requested view path is invalid.'
            );
        }

        if ($this->isUserModule()) {
            $file = $this->userModuleFile(
                '/views/' . $view . '.php'
            );
        } else {
            $file = APPROOT
                . '/views/'
                . $view
                . '.php';
        }

        if ($file !== null && is_file($file)) {
            $render_md = $this->render_md;
            $SITE = $GLOBALS['SITE'] ?? [];

            if (is_array($data)) {
                extract(
                    $data,
                    EXTR_SKIP
                );
            }

            require $file;
            return;
        }

        /*
         * User modules use the Core error presentation when their own
         * requested view cannot be resolved.
         */
        if ($this->isUserModule()) {
            $message = "View '{$view}' is currently broken.";

            $data = [
                'message' => $message,
            ];

            $errorFile = APPROOT
                . '/views/errors/error_page.php';

            if (is_file($errorFile)) {
                extract(
                    $data,
                    EXTR_SKIP
                );

                require $errorFile;
                exit;
            }

            http_response_code(500);
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            exit;
        }

        $this->error_page(
            "View '{$view}' is currently broken."
        );
    }

    /**
     * Load a model.
     *
     * Core controllers resolve models from /app/models.
     *
     * User module controllers resolve models from:
     *
     * /user/modules/{module}/models
     *
     * @param string $model Model class name.
     *
     * @return object
     */
    public function model($model)
    {
        $model = (string) $model;

        if (
            !preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $model
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid model name.'
            );
        }

        if ($this->isUserModule()) {
            $file = $this->userModuleFile(
                '/models/' . $model . '.php'
            );
        } else {
            $file = APPROOT
                . '/models/'
                . $model
                . '.php';
        }

        if ($file === null || !is_file($file)) {
            die(
                'Model '
                . htmlspecialchars(
                    $model,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . ' not found.'
            );
        }

        require_once $file;

        if (!class_exists($model, false)) {
            die(
                'Model class '
                . htmlspecialchars(
                    $model,
                    ENT_QUOTES,
                    'UTF-8'
                )
                . ' not found.'
            );
        }

        return new $model();
    }

    /**
     * Determine whether a user module controller declares public admin().
     * The controller file is tokenized, never executed during discovery.
     */
    protected function moduleDeclaresPublicAdmin(string $module): bool
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $module)) {
            return false;
        }

        $modulesRoot = realpath(USERROOT . '/modules');
        $modulePath = USERROOT . '/modules/' . $module;
        $moduleRoot = is_link($modulePath) ? false : realpath($modulePath);
        $controllerPath = $modulePath . '/controllers/' . $module . '.php';
        $resolvedController = is_link($controllerPath)
            ? false
            : realpath($controllerPath);

        if (
            $modulesRoot === false
            || $moduleRoot === false
            || $resolvedController === false
            || !str_starts_with($moduleRoot, $modulesRoot . DIRECTORY_SEPARATOR)
            || !str_starts_with($resolvedController, $moduleRoot . DIRECTORY_SEPARATOR)
            || !is_file($resolvedController)
        ) {
            return false;
        }

        $source = file_get_contents($resolvedController);

        if (!is_string($source)) {
            return false;
        }

        $tokens = token_get_all($source);
        $depth = 0;
        $classDepth = null;
        $expectClassName = false;
        $pendingTargetClass = false;
        $visibility = T_PUBLIC;

        foreach ($tokens as $index => $token) {
            if (is_string($token)) {
                if ($token === '{') {
                    $depth++;

                    if ($pendingTargetClass) {
                        $classDepth = $depth;
                        $pendingTargetClass = false;
                        $visibility = T_PUBLIC;
                    }
                } elseif ($token === '}') {
                    if ($classDepth === $depth) {
                        $classDepth = null;
                    }

                    $depth--;
                } elseif ($token === ';' && $classDepth === $depth) {
                    $visibility = T_PUBLIC;
                }

                continue;
            }

            [$id, $text] = $token;

            if ($id === T_CLASS) {
                $expectClassName = true;
                continue;
            }

            if ($expectClassName && $id === T_STRING) {
                $pendingTargetClass = strtolower($text) === $module;
                $expectClassName = false;
                continue;
            }

            if ($classDepth === null || $depth !== $classDepth) {
                continue;
            }

            if (in_array($id, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                $visibility = $id;
                continue;
            }

            if ($id !== T_FUNCTION) {
                continue;
            }

            for ($next = $index + 1, $count = count($tokens); $next < $count; $next++) {
                $candidate = $tokens[$next];

                if (is_array($candidate) && $candidate[0] === T_STRING) {
                    if (strtolower($candidate[1]) === 'admin') {
                        return $visibility === T_PUBLIC;
                    }

                    break;
                }

                if (is_string($candidate) && $candidate === '(') {
                    break;
                }
            }

            $visibility = T_PUBLIC;
        }

        return false;
    }

    /**
     * Load a model explicitly owned by another user module.
     */
    public function module_model(string $module, string $model): object
    {
        if (
            !$this->isUserModule()
            || !preg_match('/^[a-z][a-z0-9_]{1,62}$/', $module)
            || !preg_match('/^[a-z][a-z0-9_]*$/', $model)
        ) {
            throw new InvalidArgumentException('Invalid module model request.');
        }

        $modulesRoot = realpath(USERROOT . '/modules');
        $modulePath = USERROOT . '/modules/' . $module;
        $moduleRoot = is_link($modulePath) ? false : realpath($modulePath);
        $modelPath = $modulePath . '/models/' . $model . '.php';
        $resolvedModel = is_link($modelPath) ? false : realpath($modelPath);

        if (
            $modulesRoot === false
            || $moduleRoot === false
            || $resolvedModel === false
            || !str_starts_with($moduleRoot, $modulesRoot . DIRECTORY_SEPARATOR)
            || !str_starts_with($resolvedModel, $moduleRoot . DIRECTORY_SEPARATOR)
            || !is_file($resolvedModel)
        ) {
            throw new RuntimeException("Module model '{$module}/{$model}' was not found.");
        }

        require_once $resolvedModel;

        if (!class_exists($model, false)) {
            throw new RuntimeException("Module model class '{$model}' was not found.");
        }

        $reflection = new ReflectionClass($model);

        if (realpath((string) $reflection->getFileName()) !== $resolvedModel) {
            throw new RuntimeException("Module model class '{$model}' has conflicting ownership.");
        }

        return $reflection->newInstance();
    }

    /**
     * Render the Core error page.
     *
     * @param string $message Error message.
     *
     * @return never
     */
    public function error_page($message): never
    {
        $code = http_response_code();

        if ($code < 400 || $code > 599) {
            $code = 500;
        }

        $titles = [
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            500 => 'Internal Error',
            503 => 'Service Unavailable',
        ];

        error_handler::respond(
            $code,
            $titles[$code] ?? 'Request Error',
            (string) $message
        );
    }

    /**
     * Return the current user module root.
     *
     * @return string
     */
    private function userModuleRoot(): string
    {
        if ($this->module_context === null) {
            throw new RuntimeException(
                'User module context is not established.'
            );
        }

        return USERROOT
            . '/modules/'
            . $this->module_context;
    }

    /**
     * Resolve an unlinked file confined to the current user module.
     *
     * @return string|null
     */
    private function userModuleFile(string $relativePath): ?string
    {
        $moduleRootPath = $this->userModuleRoot();

        if (is_link($moduleRootPath)) {
            return null;
        }

        $moduleRoot = realpath($moduleRootPath);
        $candidate = $moduleRootPath . $relativePath;

        if ($moduleRoot === false || is_link($candidate)) {
            return null;
        }

        $resolved = realpath($candidate);

        if (
            $resolved === false
            || !str_starts_with(
                $resolved,
                $moduleRoot . DIRECTORY_SEPARATOR
            )
            || !is_file($resolved)
        ) {
            return null;
        }

        return $resolved;
    }

    /**
     * Validate a relative view path.
     *
     * @param string $path Relative view path.
     *
     * @return bool
     */
    private function validRelativePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (
            str_contains($path, '..')
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
        ) {
            return false;
        }

        return (bool) preg_match(
            '#^[a-zA-Z0-9_/-]+$#',
            $path
        );
    }
}
/* [End AI:GPT-5.6 Sol] */
