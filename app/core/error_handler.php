<?php

/**
 * ChAoS MVC Core Error Handler
 */
final class error_handler
{
    private static bool $registered = false;
    private static bool $handling = false;
    private static int $bufferLevel = 0;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        self::$bufferLevel = ob_get_level();
        ob_start();

        set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file,
                int $line
            ): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                throw new ErrorException(
                    $message,
                    0,
                    $severity,
                    $file,
                    $line
                );
            }
        );

        set_exception_handler(
            static function (Throwable $error): void {
                self::respond(
                    500,
                    'Internal Error',
                    'The request could not be completed.',
                    $error
                );
            }
        );

        register_shutdown_function(
            static function (): void {
                $last = error_get_last();

                if (
                    !is_array($last)
                    || !in_array(
                        (int) ($last['type'] ?? 0),
                        [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR],
                        true
                    )
                ) {
                    return;
                }

                self::respond(
                    500,
                    'Internal Error',
                    'The request could not be completed.',
                    new ErrorException(
                        (string) ($last['message'] ?? 'Fatal PHP error'),
                        0,
                        (int) ($last['type'] ?? E_ERROR),
                        (string) ($last['file'] ?? 'unknown'),
                        (int) ($last['line'] ?? 0)
                    )
                );
            }
        );
    }

    /** @return never */
    public static function respond(
        int $code,
        string $title,
        string $message,
        ?Throwable $error = null
    ): never {
        if (self::$handling) {
            self::emergencyResponse($code, $title, $message);
        }

        self::$handling = true;
        $reference = bin2hex(random_bytes(6));

        if ($error !== null) {
            error_log(
                '[ChAoS Error ' . $reference . '] '
                . get_class($error) . ': ' . $error->getMessage()
                . ' in ' . $error->getFile() . ':' . $error->getLine()
                . PHP_EOL . $error->getTraceAsString()
            );
        }

        self::discardBufferedOutput();

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        $data = [
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'reference' => $error !== null ? $reference : null,
        ];
        $view = defined('APPROOT')
            ? APPROOT . '/views/errors/error_page.php'
            : '';

        if ($view !== '' && is_file($view)) {
            try {
                $SITE = $GLOBALS['SITE'] ?? [];
                require $view;
                exit;
            } catch (Throwable $renderError) {
                error_log('[ChAoS Error View] ' . $renderError->getMessage());
            }
        }

        self::emergencyResponse($code, $title, $message, $reference);
    }

    /** @return never */
    private static function emergencyResponse(
        int $code,
        string $title,
        string $message,
        ?string $reference = null
    ): never {
        self::discardBufferedOutput();

        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeReference = $reference !== null
            ? htmlspecialchars($reference, ENT_QUOTES, 'UTF-8')
            : null;

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeCode . ' — ' . $safeTitle . '</title></head>'
            . '<body><main><h1>' . $safeCode . '</h1><h2>' . $safeTitle . '</h2>'
            . '<p>' . $safeMessage . '</p>';

        if ($safeReference !== null) {
            echo '<p>Reference: ' . $safeReference . '</p>';
        }

        echo '</main></body></html>';
        exit;
    }

    private static function discardBufferedOutput(): void
    {
        while (ob_get_level() > self::$bufferLevel) {
            ob_end_clean();
        }
    }

    public function index(): never
    {
        $this->not_found();
    }

    public function bad_request(): never
    {
        self::respond(400, 'Bad Request', 'The request could not be understood.');
    }

    public function unauthorized(): never
    {
        self::respond(403, 'Forbidden', 'Access denied.');
    }

    public function illegal_entity(): never
    {
        self::respond(403, 'Security Protocol Active', 'Access denied.');
    }

    public function not_found(): never
    {
        self::respond(404, 'Not Found', 'The requested resource does not exist.');
    }

    public function server_error(): never
    {
        self::respond(500, 'Internal Error', 'The request could not be completed.');
    }

    public function service_unavailable(): never
    {
        self::respond(503, 'Service Unavailable', 'The service is temporarily unavailable.');
    }
}
