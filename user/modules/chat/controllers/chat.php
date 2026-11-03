<?php
declare(strict_types=1);

/** Website chat and the local-only Digit HTTP transport. */
final class chat extends controller
{
    private function json(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function index(array $params = []): void
    {
        $this->require_admin(9);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->view('index');
            return;
        }
        $this->require_csrf();
        $message = $_POST['message'] ?? null;
        if (!is_string($message)) {
            $this->json(422, ['ok' => false, 'error' => 'Invalid message.']);
            return;
        }
        try {
            $id = $this->model('chat_model')->submit(
                $message, hash('sha256', session_id())
            );
            $this->json(202, ['ok' => true, 'id' => $id]);
        } catch (InvalidArgumentException $e) {
            $this->json(422, ['ok' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Digit Chat enqueue: ' . $e->getMessage());
            $this->json(503, ['ok' => false, 'error' => 'Chat delivery is unavailable.']);
        }
    }

    public function status(array $params = []): void
    {
        $this->require_admin(9);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            $this->json(405, ['ok' => false, 'error' => 'Method not allowed.']);
            return;
        }
        try {
            $state = $this->model('chat_model')->status(
                (string) ($_GET['id'] ?? ''), hash('sha256', session_id())
            );
            if ($state === null) {
                $this->json(404, ['ok' => false, 'error' => 'Message not found.']);
                return;
            }
            $this->json(200, ['ok' => true] + $state);
        } catch (Throwable $e) {
            error_log('Digit Chat status: ' . $e->getMessage());
            $this->json(503, ['ok' => false, 'error' => 'Chat status is unavailable.']);
        }
    }

    public function transport(array $params = []): void
    {
        // Digit 1.0.1 has no transport credential. Strict loopback-source gate;
        // deployment must additionally restrict this route at the web server.
        if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
            $this->json(403, ['error' => 'Forbidden.']);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(405, ['error' => 'Method not allowed.']);
            return;
        }
        if (!str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $this->json(415, ['error' => 'JSON required.']);
            return;
        }
        $raw = file_get_contents('php://input', false, null, 0, 4097);
        if (!is_string($raw) || strlen($raw) > 4096) {
            $this->json(413, ['error' => 'Request too large.']);
            return;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !is_string($payload['type'] ?? null)) {
            $this->json(400, ['error' => 'Invalid request.']);
            return;
        }
        try {
            $model = $this->model('chat_model');
            if ($payload['type'] === 'poll') {
                $pending = $model->pending();
                if ($pending === null) {
                    http_response_code(204);
                    header('Cache-Control: no-store');
                    return;
                }
                $this->json(200, $pending);
                return;
            }
            if ($payload['type'] === 'reply' &&
                is_string($payload['id'] ?? null) &&
                is_string($payload['status'] ?? null) &&
                is_string($payload['message'] ?? null)) {
                if (!$model->receive($payload['id'], $payload['status'], $payload['message'])) {
                    $this->json(409, ['error' => 'Unknown or completed request.']);
                    return;
                }
                $this->json(200, ['ok' => true]);
                return;
            }
            $this->json(400, ['error' => 'Invalid transport operation.']);
        } catch (InvalidArgumentException $e) {
            $this->json(422, ['error' => 'Invalid transport payload.']);
        } catch (Throwable $e) {
            error_log('Digit Chat transport: ' . $e->getMessage());
            $this->json(503, ['error' => 'Transport unavailable.']);
        }
    }
}
