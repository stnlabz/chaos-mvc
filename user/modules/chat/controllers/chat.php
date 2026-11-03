<?php
declare(strict_types=1);

/**
 * Digit Chat web entry point.
 * Keep all transport operations in chat_model and all access checks here.
 */
final class chat extends controller
{
    public function index(array $params = []): void
    {
        // Until native transport qualification and an approved access policy,
        // this route is exclusively accessible to a level-9 administrator.
        $this->require_admin(9);

        $ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        $model = $this->model('chat_model');
        $reply = null;
        $error = null;
        $submitted = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();

            if (!$model->isEnabled()) {
                http_response_code(503);
                $error = 'Chat is not available yet.';
            } else {
                $submitted = is_string($_POST['message'] ?? null)
                    ? $_POST['message']
                    : '';

                try {
                    $reply = $model->ask($submitted);
                } catch (InvalidArgumentException $exception) {
                    http_response_code(422);
                    $error = $exception->getMessage();
                } catch (RuntimeException $exception) {
                    http_response_code(503);
                    // These are module-owned diagnostic messages, not raw socket paths.
                    $error = $exception->getMessage();
                    error_log('Digit Chat transport: ' . $exception->getMessage());
                } catch (Throwable $exception) {
                    http_response_code(503);
                    error_log('Digit Chat unexpected error: ' . get_class($exception) . ': ' . $exception->getMessage());
                    $error = 'An unexpected chat processing error occurred. Check the server error log.';
                }
            }
        }

        if ($ajax && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'ok' => $error === null,
                'reply' => $reply,
                'error' => $error,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        $this->view('index', [
            'enabled' => $model->isEnabled(),
            'reply' => $reply,
            'error' => $error,
            'submitted' => $submitted,
        ]);
    }
}
