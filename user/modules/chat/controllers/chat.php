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
                } catch (Throwable $exception) {
                    http_response_code(503);
                    error_log('Digit Chat transport failure: ' . get_class($exception));
                    $error = 'Chat is temporarily unavailable.';
                }
            }
        }

        $this->view('index', [
            'enabled' => $model->isEnabled(),
            'reply' => $reply,
            'error' => $error,
            'submitted' => $submitted,
        ]);
    }
}
