<?php
/**
 * Router
 * Responsible only for dispatching URL → controller → method → params.
 * No database calls belong here.
 * LOCKED CORE FILE
 * Core Routing Infrastructure
 * Modifications require explicit authorization.
 *
 * Security Maintenance
 *
 * CMSEC-2026-4827-F — Protected Router Action Boundary
 *
 * Request-derived method names must resolve to explicitly authorized,
 * public controller actions. PHP method existence or visibility alone
 * does not establish HTTP routing authority.
 *
 * [Human:Mei | 2026-03-13 03:45:00 UTC]
 */

class router
{
    protected $controller = 'home';
    protected $method = 'index';
    protected $params = [];

    /**
     * Initialize routing.
     *
     * CMSEC-2026-4827-F
     */
    public function __construct()
    {
        $this->dispatch();
    }

    /**
     * Main dispatch logic.
     *
     * CMSEC-2026-4827-F
     *
     * Request-derived methods are accepted only when they pass the
     * explicit router action boundary.
     */
    private function dispatch()
    {
        $url = $this->parseUrl();


        /* -------------------------------------------------
           CLEAN URL ALIASES
           /login   → /auth/login
           /signup  → /auth/register
           /logout  → /auth/logout
           /forgot-password → /auth/forgot_password
           /reset-password → /auth/reset_password
        --------------------------------------------------*/

        if (!empty($url[0])) {

            $aliases = [
                'login'  => ['auth', 'login'],
                'signup' => ['auth', 'register'],
                'logout' => ['auth', 'logout'],
                'forgot-password' => ['auth', 'forgot_password'],
                'reset-password'  => ['auth', 'reset_password']
            ];

            if (isset($aliases[$url[0]])) {

                $map = $aliases[$url[0]];

                /*
                 * CMSEC-2026-4827-F1
                 *
                 * Preserve every path segment following a clean alias.
                 * This keeps reset-password bearer tokens and any future
                 * alias parameters in the controller parameter list.
                 */
                $url = array_merge(
                    $map,
                    array_slice($url, 1)
                );
            }
        }


        /* -------------------------------------------------
           CONTROLLER
        --------------------------------------------------*/
        /* [AI:GPT | 2026-03-14 18:15:00 UTC] */
        /* [HUMAN: MEI | APPROVE | 2026-03-15 18:17 UTC] */
        if (isset($url[0]) && $url[0] !== '') {

            $controller_file = APPROOT . '/controllers/' . $url[0] . '.php';

            if (file_exists($controller_file)) {

                // Normal controller route
                $this->controller = $url[0];
                unset($url[0]);

            } else {

                // Attempt DB-driven module resolution
                require_once APPROOT . '/models/modules_model.php';
                $modules = new modules_model();

                if ($modules->get_by_slug($url[0])) {

                // Route request through page controller
                $this->controller = 'page';
                $this->method = 'index';

            } else {

                // Nothing matched → true 404
                (new error_handler())->not_found();
                return;

            }
        }
    }
    /* [End AI:GPT] */

        $controller_path = APPROOT . '/controllers/' . $this->controller . '.php';

        if (!file_exists($controller_path)) {
            /** 
             * Setting the proper error handler
             * [Human:Mei | 2026-03-13 03:35:00 UTC]
            */
            (new error_handler())->not_found();
            return;
        }

        require_once $controller_path;

        if (!class_exists($this->controller)) {
            /** 
             * Setting the proper error handler
             * [Human:Mei | 2026-03-13 03:35:00 UTC]
            */
            (new error_handler())->not_found();
            return;
        }

        $this->controller = new $this->controller;


        /* -------------------------------------------------
           METHOD
        --------------------------------------------------*/

        /*
         * CMSEC-2026-4827-F
         *
         * Previous request-derived dispatch behavior retained as a
         * commented security-maintenance record. method_exists() alone
         * does not establish that a method is public or intentionally
         * exposed as an HTTP endpoint.
         *
         * if (isset($url[1])) {
         *     if (method_exists($this->controller, $url[1])) {
         *         $this->method = $url[1];
         *         unset($url[1]);
         *     }
         * }
         */

        /*
         * CMSEC-2026-4827-F2
         * CMSEC-2026-4827-F3
         * CMSEC-2026-4827-F4
         *
         * Resolve established parameter-style routes before treating the
         * second URL segment as a requested controller method. Direct
         * methods still require explicit authorization and public visibility.
         */
        if (isset($url[1])) {
            $requested_method = (string) $url[1];

            if (
                $this->controller instanceof posts
                && !$this->isRoutableAction(
                    $this->controller,
                    $requested_method
                )
            ) {
                /*
                 * CMSEC-2026-4827-F3
                 *
                 * /posts/{slug} delegates to posts::show({slug}). The slug
                 * remains in the URL so it becomes the first parameter.
                 */
                $this->method = 'show';
            } elseif (
                $this->controller instanceof admin
                && !$this->isRoutableAction(
                    $this->controller,
                    $requested_method
                )
            ) {
                /*
                 * CMSEC-2026-4827-F2
                 *
                 * /admin/{module} delegates through admin::index(). Only
                 * controllers with an explicitly public admin action qualify.
                 */
                if (!$this->isAdminModuleRoute($requested_method)) {
                    (new error_handler())->not_found();
                    return;
                }

                $this->method = 'index';
            } elseif (
                $this->isRoutableAction(
                    $this->controller,
                    $requested_method
                )
            ) {
                $this->method = $requested_method;
                unset($url[1]);
            } else {
                (new error_handler())->not_found();
                return;
            }
        }


        /* -------------------------------------------------
           PARAMS
        --------------------------------------------------*/

        $this->params = $url ? array_values($url) : [];


        /* -------------------------------------------------
           CLEAN URL SUPPORT
           Allows:
           /posts/my-post-slug
           instead of:
           /posts/show/my-post-slug
        --------------------------------------------------*/

        if (
            $this->controller instanceof posts &&
            $this->method === 'index' &&
            count($this->params) === 1
        ) {
            /*
             * CMSEC-2026-4827-F3
             *
             * Preserve the established clean post-slug route while
             * verifying that posts::show is an authorized action.
             */
            if (
                !$this->isRoutableAction(
                    $this->controller,
                    'show'
                )
            ) {
                (new error_handler())->not_found();
                return;
            }

            $this->method = 'show';
        }


        /* -------------------------------------------------
           FINAL DISPATCH
        --------------------------------------------------*/

        /*
         * CMSEC-2026-4827-F
         *
         * Previous final method_exists() boundary retained as a
         * commented security-maintenance record.
         *
         * if (!method_exists($this->controller, $this->method)) {
         *     (new error_handler())->not_found();
         *     return;
         * }
         */

        /*
         * CMSEC-2026-4827-F
         *
         * Revalidate the final action after aliases, module routing,
         * parameter normalization, and clean post-slug translation.
         */
        if (
            !$this->isRoutableAction(
                $this->controller,
                $this->method
            )
        ) {
            /** 
             * Setting the proper error handler
             * [Human:Mei | 2026-03-13 03:35:00 UTC]
            */
            (new error_handler())->not_found();
            return;
        }

        call_user_func_array(
            [$this->controller, $this->method],
            [$this->params]
        );
    }


    /**
     * Determine whether a segment names an administrative module route.
     *
     * CMSEC-2026-4827-F2
     *
     * Administration module routes are parameters to admin::index(), not
     * methods on the admin controller. A module qualifies only when its
     * controller exists and declares a public admin action.
     *
     * @param string $module Requested module controller name.
     * @return bool
     */
    private function isAdminModuleRoute($module)
    {
        if (
            !preg_match('/^[a-zA-Z0-9_]+$/', $module)
            || $module === 'admin'
        ) {
            return false;
        }

        $controller_path =
            APPROOT
            . '/controllers/'
            . $module
            . '.php';

        if (!file_exists($controller_path)) {
            return false;
        }

        require_once $controller_path;

        if (
            !class_exists($module)
            || !method_exists($module, 'admin')
        ) {
            return false;
        }

        $reflection = new ReflectionMethod(
            $module,
            'admin'
        );

        return $reflection->isPublic();
    }


    /**
     * Determine whether a controller method is an authorized HTTP action.
     *
     * CMSEC-2026-4827-F4
     *
     * Every controller may expose its public index action. Non-index
     * actions must be listed explicitly. The reflection check independently
     * guarantees that private and protected methods cannot be dispatched.
     *
     * @param object $controller Controller instance.
     * @param string $method Requested action.
     * @return bool
     */
    private function isRoutableAction(
        $controller,
        $method
    ) {
        $controller_name = get_class($controller);

        $routes = [
            'accounts' => [
                'admin',
                'create',
                'delete',
                'email',
                'password',
            ],
            'admin' => [
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
                'collect',
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

        $authorized =
            $method === 'index'
            || in_array(
                $method,
                $routes[$controller_name] ?? [],
                true
            );

        if (
            !$authorized
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

        return $reflection->isPublic();
    }


    /**
     * Parse URL from query string
     */
    private function parseUrl()
    {
        if (isset($_GET['url'])) {

            $url = rtrim($_GET['url'], '/');

            $url = filter_var($url, FILTER_SANITIZE_URL);

            return explode('/', $url);
        }

        return [];
    }


    /**
     * Basic 404 handler
     */
     /**
      * Removing to use the proper error handler
      * [Human:Mei | 2026-03-13 03:35:00 UTC]
    private function error404()
    {
        header("HTTP/1.0 404 Not Found");

        $error_file = APPROOT . '/views/errors/error_page.php';

        if (file_exists($error_file)) {
            require_once $error_file;
        } else {
            echo "404 - Page not found.";
        }

        exit;
    }
    */
}
