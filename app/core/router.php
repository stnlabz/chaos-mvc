<?php

/**
 * Router
 *
 * Resolves URL → ownership → controller → method → params.
 *
 * Core controllers are resolved from /app/controllers.
 * User modules are resolved from /user/modules.
 *
 * No database calls belong here except the established DB-driven
 * page resolution fallback.
 *
 * LOCKED CORE FILE
 * Core Routing Infrastructure
 *
 * [Human:Mei | 2026-03-13 03:45:00 UTC]
 */

/* [AI:GPT-5.6 Sol | 2026-08-26 UTC] */
class router
{
    /**
     * Controller class name.
     *
     * @var string
     */
    protected $controller = 'home';

    /**
     * Controller method.
     *
     * @var string
     */
    protected $method = 'index';

    /**
     * Route parameters.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Resolved controller ownership.
     *
     * @var string
     */
    protected $controller_scope = 'core';

    /**
     * Resolved user module slug.
     *
     * @var string|null
     */
    protected $module_context = null;

    /**
     * Initialize routing.
     */
    public function __construct()
    {
        $this->dispatch();
    }

    /**
     * Dispatch the current request.
     *
     * CMSEC-2026-4830-C — Exact controller class ownership
     *
     * @return void
     */
    private function dispatch(): void
    {
        $url = $this->parseUrl();

        $url = $this->applyAliases($url);

        /*
         * Resolve controller ownership.
         */
        if ($url === []) {
            $homeController = USERROOT
                . '/modules/home/controllers/home.php';

            if (!$this->isConfinedUserModuleFile('home', $homeController)) {
                $this->notFound();
                return;
            }

            $this->controller = 'home';
            $this->controller_scope = 'user';
            $this->module_context = 'home';
        } elseif (
            isset($url[0])
            && $url[0] !== ''
        ) {
            $requestedController = (string) $url[0];

            if (
                !$this->validControllerName(
                    $requestedController
                )
            ) {
                $this->notFound();
                return;
            }

            $coreController = $requestedController === 'home'
                ? ''
                : APPROOT
                    . '/controllers/'
                    . $requestedController
                    . '.php';

            $userController = USERROOT
                . '/modules/'
                . $requestedController
                . '/controllers/'
                . $requestedController
                . '.php';

            /*
             * Core owns the namespace first.
             *
             * A user module cannot silently override a Core controller
             * by using the same slug.
             */
            if (is_file($coreController)) {
                $this->controller = $requestedController;
                $this->controller_scope = 'core';

                unset($url[0]);
            } elseif (
                $this->isConfinedUserModuleFile(
                    $requestedController,
                    $userController
                )
            ) {
                $this->controller = $requestedController;
                $this->controller_scope = 'user';
                $this->module_context = $requestedController;

                unset($url[0]);
            } else {
                /*
                 * Preserve the established DB-driven page/module fallback.
                 */
                require_once APPROOT
                    . '/models/modules_model.php';

                $modules = new modules_model();

                if (
                    $modules->get_by_slug(
                        $requestedController
                    )
                ) {
                    $this->controller = 'page';
                    $this->controller_scope = 'core';
                    $this->method = 'index';
                } else {
                    $this->notFound();
                    return;
                }
            }
        }

        $controllerPath = $this->controllerPath();

        if (!is_file($controllerPath)) {
            $this->notFound();
            return;
        }

        /*
         * CMSEC-2026-4830-C — Exact controller class ownership
         */
        if (
            $this->controller_scope === 'user'
            && class_exists((string) $this->controller, true)
        ) {
            $this->notFound();
            return;
        }

        require_once $controllerPath;

        if (
            !class_exists(
                $this->controller,
                false
            )
        ) {
            $this->notFound();
            return;
        }

        $controllerReflection = new ReflectionClass((string) $this->controller);

        if (
            realpath((string) $controllerReflection->getFileName())
            !== realpath($controllerPath)
        ) {
            $this->notFound();
            return;
        }

        $controllerClass = $this->controller;

        /*
         * CMSEC-2026-4830-I — Establish user ownership before construction.
         *
         * A module constructor may legitimately load its own model or other
         * module-local service. Instantiating it normally would run that
         * constructor while the base controller still identified itself as
         * Core, causing those lookups to resolve against /app.
         */
        if (
            $this->controller_scope === 'user'
            && $this->module_context !== null
        ) {
            $controllerObject = $controllerReflection
                ->newInstanceWithoutConstructor();

            $controllerObject->setModuleContext(
                $this->module_context
            );

            $constructor = $controllerReflection->getConstructor();

            if ($constructor !== null) {
                if (
                    !$constructor->isPublic()
                    || $constructor->getNumberOfRequiredParameters() > 0
                ) {
                    $this->notFound();
                    return;
                }

                $constructor->invoke($controllerObject);
            }
        } else {
            $controllerObject = $controllerReflection->newInstance();
        }

        $this->controller = $controllerObject;

        /*
         * Resolve method.
         */
        if (isset($url[1])) {
            $requestedMethod = (string) $url[1];

            if (
                $this->controller instanceof posts
                && !$this->isRoutableAction(
                    $this->controller,
                    $requestedMethod
                )
            ) {
                /*
                 * /posts/{slug}
                 */
                $this->method = 'show';
            } elseif (
                $this->controller instanceof admin
                && !$this->isRoutableAction(
                    $this->controller,
                    $requestedMethod
                )
            ) {
                /*
                 * /admin/{module}
                 *
                 * admin::index() remains the admin dispatcher.
                 */
                if ((int) ($_SESSION['user_level'] ?? 0) < 7) {
                    header('Location: /auth/login');
                    exit;
                }

                if (
                    !$this->isAdminModuleRoute(
                        $requestedMethod
                    )
                ) {
                    $this->notFound();
                    return;
                }

                $this->method = 'index';
            } elseif (
                $this->controller_scope === 'user'
                && $this->moduleUsesIndexParameters()
                && !$this->isRoutableAction(
                    $this->controller,
                    $requestedMethod
                )
            ) {
                /*
                 * /{module}/{value}
                 *
                 * Service modules may explicitly route the remaining URL
                 * segments to index() as parameters. The requested value is
                 * deliberately retained in $url for parameter collection.
                 */
                $this->method = 'index';
            } elseif (
                $this->isRoutableAction(
                    $this->controller,
                    $requestedMethod
                )
            ) {
                $this->method = $requestedMethod;
                unset($url[1]);
            } else {
                $this->notFound();
                return;
            }
        }

        /*
         * Parameters.
         */
        $this->params = $url
            ? array_values($url)
            : [];

        /*
         * Preserve clean post-slug routing.
         */
        if (
            $this->controller instanceof posts
            && $this->method === 'index'
            && count($this->params) === 1
        ) {
            if (
                !$this->isRoutableAction(
                    $this->controller,
                    'show'
                )
            ) {
                $this->notFound();
                return;
            }

            $this->method = 'show';
        }

        /*
         * Final dispatch boundary.
         */
        if (
            !$this->isRoutableAction(
                $this->controller,
                $this->method
            )
        ) {
            $this->notFound();
            return;
        }

        call_user_func_array(
            [
                $this->controller,
                $this->method,
            ],
            [
                $this->params,
            ]
        );
    }

    /**
     * Apply established clean URL aliases while preserving parameters.
     *
     * @param array $url Parsed URL.
     *
     * @return array
     */
    private function applyAliases(array $url): array
    {
        if (empty($url[0])) {
            return $url;
        }

        $aliases = [
            'login' => [
                'auth',
                'login',
            ],
            'signup' => [
                'auth',
                'register',
            ],
            'logout' => [
                'auth',
                'logout',
            ],
            'forgot-password' => [
                'auth',
                'forgot_password',
            ],
            'reset-password' => [
                'auth',
                'reset_password',
            ],
        ];

        $alias = (string) $url[0];

        if (!isset($aliases[$alias])) {
            return $url;
        }

        return array_merge(
            $aliases[$alias],
            array_slice(
                $url,
                1
            )
        );
    }

    /**
     * Return the resolved controller file.
     *
     * @return string
     */
    private function controllerPath(): string
    {
        if (
            $this->controller_scope === 'user'
            && $this->module_context !== null
        ) {
            return USERROOT
                . '/modules/'
                . $this->module_context
                . '/controllers/'
                . $this->module_context
                . '.php';
        }

        $controllerName = is_object(
            $this->controller
        )
            ? get_class($this->controller)
            : (string) $this->controller;

        return APPROOT
            . '/controllers/'
            . $controllerName
            . '.php';
    }

    /**
     * Determine whether a segment names an administrative module route.
     *
     * CMSEC-2026-4830-C — Exact controller class ownership
     *
     * Core controllers are resolved first.
     * User modules are resolved only when no Core controller owns
     * the requested module name.
     *
     * @param string $module Requested module slug.
     *
     * @return bool
     */
    private function isAdminModuleRoute(
        string $module
    ): bool {
        if (
            !$this->validControllerName($module)
            || $module === 'admin'
        ) {
            return false;
        }

        $coreController = APPROOT
            . '/controllers/'
            . $module
            . '.php';

        if (is_file($coreController)) {
            require_once $coreController;

            return $this->classHasPublicAdmin(
                $module,
                $coreController
            );
        }

        $userController = USERROOT
            . '/modules/'
            . $module
            . '/controllers/'
            . $module
            . '.php';

        if (!$this->isConfinedUserModuleFile($module, $userController)) {
            return false;
        }

        if (class_exists($module, true)) {
            return false;
        }

        require_once $userController;

        return $this->classHasPublicAdmin(
            $module,
            $userController
        );
    }

    /**
     * Determine whether a class exposes a public admin action.
     *
     * CMSEC-2026-4830-C — Exact controller class ownership
     *
     * @param string $class Controller class.
     *
     * @return bool
     */
    private function classHasPublicAdmin(
        string $class,
        ?string $expectedFile = null
    ): bool {
        if (
            !class_exists(
                $class,
                false
            )
            || !method_exists(
                $class,
                'admin'
            )
        ) {
            return false;
        }

        $reflection = new ReflectionMethod(
            $class,
            'admin'
        );

        return $reflection->isPublic()
            && (
                $expectedFile === null
                || realpath((string) $reflection->getFileName())
                    === realpath($expectedFile)
            );
    }

    /**
     * Determine whether a controller method is an authorized HTTP action.
     *
     * CMSEC-2026-4830-D — Explicit user-module route declarations
     *
     * Core controllers retain the explicit Core route table.
     *
     * User modules may expose public methods declared directly on their
     * own controller class. Inherited base-controller helpers are never
     * treated as user-module HTTP actions.
     *
     * @param object $controller Controller instance.
     * @param string $method     Requested action.
     *
     * @return bool
     */
    private function isRoutableAction(
        $controller,
        string $method
    ): bool {
        if (
            !preg_match(
                '/^[a-zA-Z0-9_]+$/',
                $method
            )
            || !method_exists(
                $controller,
                $method
            )
        ) {
            return false;
        }

        $reflection = new ReflectionMethod(
            $controller,
            $method
        );

        if (!$reflection->isPublic()) {
            return false;
        }

        /*
         * User-land routing.
         *
         * Only methods declared by the module controller itself are
         * routable. Inherited controller helpers remain internal.
         */
        if (
            $controller instanceof controller
            && $controller->isUserModule()
        ) {
            if ($method === 'admin') {
                return false;
            }

            $module = (string) $controller->getModuleContext();
            $metadataPath = USERROOT
                . '/modules/' . $module . '/module.json';
            $raw = is_file($metadataPath)
                ? file_get_contents($metadataPath)
                : false;
            $metadata = is_string($raw) ? json_decode($raw, true) : null;
            $routes = is_array($metadata) ? ($metadata['routes'] ?? []) : [];

            return $reflection->getDeclaringClass()->getName()
                    === get_class($controller)
                && is_array($routes)
                && in_array($method, $routes, true);
        }

        /*
         * Core routing.
         */
        $controllerName = get_class(
            $controller
        );

        $routes = [
            'accounts' => [
                'admin',
                'create',
                'delete',
                'email',
                'password',
            ],
            'admin' => [
                /*
                 * CMSEC-2026-4833-A
                 *
                 * Authenticated, CSRF-verified, read-only module release
                 * discovery. Installation remains a separate update action.
                 */
                'check_update',
                'update',
                'uninstall',
            ],
            'auth' => [
                'login',
                'logout',
                'register',
                'forgot_password',
                'reset_password',
                'delete',
            ],
            'developer' => [
                'example',
                'flow',
                'database',
                'markdown',
                'theme',
                'rules',
            ],
            'error_handler' => [
                'bad_request',
                'unauthorized',
                'not_found',
                'server_error',
                'service_unavailable',
            ],
            'legal' => [
                'terms',
                'privacy',
            ],
            'posts' => [
                'show',
                'admin',
                'reply',
            ],
            'updater' => [
                'admin',
                'check',
                'run',
                'status',
            ],
            'traffic' => [
                /*
                 * CMSEC-2026-4830-F — Internal traffic collector
                 *
                 * Disabled HTTP exposure retained for maintenance history:
                 * 'collect',
                 */
                'admin',
            ],
            'modules' => [
                'admin',
            ],
            'site' => [
                'admin',
            ],
            'media' => [
                'admin',
            ],
        ];

        $authorized = $method === 'index'
            || in_array(
                $method,
                $routes[$controllerName] ?? [],
                true
            );

        return $authorized;
    }

    /**
     * Determine whether the current user module explicitly accepts URL
     * segments as parameters to index().
     */
    private function moduleUsesIndexParameters(): bool
    {
        if (
            $this->controller_scope !== 'user'
            || $this->module_context === null
        ) {
            return false;
        }

        $metadataPath = USERROOT
            . '/modules/'
            . $this->module_context
            . '/module.json';

        $raw = is_file($metadataPath)
            ? file_get_contents($metadataPath)
            : false;

        $metadata = is_string($raw)
            ? json_decode($raw, true)
            : null;

        return is_array($metadata)
            && ($metadata['index_parameters'] ?? false) === true
            && $this->isRoutableAction(
                $this->controller,
                'index'
            );
    }

    /**
     * Validate a controller/module slug.
     *
     * @param string $name Controller name.
     *
     * @return bool
     */
    private function validControllerName(
        string $name
    ): bool {
        return (bool) preg_match(
            '/^[a-z][a-z0-9_]{0,62}$/',
            $name
        );
    }

    /**
     * Confirm that an executable user-module file is a real, unlinked file
     * confined to the selected module directory.
     */
    private function isConfinedUserModuleFile(
        string $module,
        string $file
    ): bool {
        $modulesRoot = realpath(USERROOT . '/modules');
        $modulePath = USERROOT . '/modules/' . $module;

        if (
            $modulesRoot === false
            || is_link($modulePath)
            || is_link($file)
        ) {
            return false;
        }

        $moduleRoot = realpath($modulePath);
        $resolvedFile = realpath($file);

        return $moduleRoot !== false
            && $resolvedFile !== false
            && str_starts_with(
                $moduleRoot,
                $modulesRoot . DIRECTORY_SEPARATOR
            )
            && str_starts_with(
                $resolvedFile,
                $moduleRoot . DIRECTORY_SEPARATOR
            )
            && is_file($resolvedFile);
    }

    /**
     * Parse the request URL.
     *
     * @return array
     */
    private function parseUrl(): array
    {
        if (!isset($_GET['url'])) {
            return [];
        }

        $url = rtrim(
            (string) $_GET['url'],
            '/'
        );

        $url = filter_var(
            $url,
            FILTER_SANITIZE_URL
        );

        return explode(
            '/',
            $url
        );
    }

    /**
     * Render the established 404 response.
     *
     * @return void
     */
    private function notFound(): void
    {
        (new error_handler())->not_found();
    }
}
/* [End AI:GPT-5.6 Sol] */
