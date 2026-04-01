<?php
/**
 * Router
 * Responsible only for dispatching URL → controller → method → params
 * No database calls belong here.
  * LOCKED CORE FILE
 * Core Routing Infrastructure
 * Modifications require explicit authorization.
 * [Human:Mei | 2026-03-13 03:45:00 UTC]
 */

class router
{
    protected $controller = 'home';
    protected $method = 'index';
    protected $params = [];

    public function __construct()
    {
        $this->dispatch();
    }

    /**
     * Main dispatch logic
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

                $url[0] = $map[0];
                $url[1] = $map[1];
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

        if (isset($url[1])) {

            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
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
            $this->method = 'show';
        }


        /* -------------------------------------------------
           FINAL DISPATCH
        --------------------------------------------------*/

        if (!method_exists($this->controller, $this->method)) {
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
