<?php
declare(strict_types=1);

/** ChAoS MVC Chat module; all persistent data and SQL belong to this module. */
final class chat extends controller
{
    private function json(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function account(): int
    {
        $account = (int) ($_SESSION['user_id'] ?? 0);
        if ($account < 1) {
            throw new RuntimeException('Authentication required.');
        }
        return $account;
    }

    private function selected(): array
    {
        $selected = $_SESSION['chat_selection'] ?? null;
        if (!is_array($selected) || ($selected['account'] ?? null) !== $this->account()) {
            return [0, 0];
        }
        return [(int) ($selected['project'] ?? 0), (int) ($selected['conversation'] ?? 0)];
    }

    private function remember(int $project, int $conversation): void
    {
        $_SESSION['chat_selection'] = [
            'account' => $this->account(),
            'project' => $project,
            'conversation' => $conversation,
        ];
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(9);
        $model = $this->model('chat_model');
        $state = $model->databaseState();
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $action = $_POST['action'] ?? '';
            try {
                if ($action === 'install_sql' && $state === 'missing') {
                    $model->installSchema();
                    header('Location: /admin/chat?status=installed');
                    exit;
                }
                if ($action === 'update_sql' && $state === 'update') {
                    $model->updateSchema();
                    header('Location: /admin/chat?status=updated');
                    exit;
                }
                throw new InvalidArgumentException('Chat database action unavailable.');
            } catch (Throwable $e) {
                error_log('Chat schema lifecycle: ' . $e->getMessage());
                $error = 'Chat database operation failed. Check the server error log.';
            }
        }
        $this->view('admin/index', [
            'database_state' => $model->databaseState(),
            'error' => $error,
            'status' => $_GET['status'] ?? null,
        ]);
    }

    public function index(array $params = []): void
    {
        $this->require_admin(7);
        $model = $this->model('chat_model');
        if ($model->databaseState() !== 'current') {
            http_response_code(503);
            $this->view('index', ['unavailable' => true]);
            return;
        }
        $account = $this->account();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
            $message = $_POST['message'] ?? null;
            try {
                if (!is_string($message)) {
                    throw new InvalidArgumentException('Invalid message.');
                }
                // Conversation identity travels in the POST body, never the URL.
                // It is verified on each request, avoiding session/tab races.
                $project = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                $conversation = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);
                if (!$project || !$conversation) {
                    throw new InvalidArgumentException('Select a conversation first.');
                }
                $displayName = $_SESSION['display_name'] ?? null;
                if (!is_string($displayName) || trim($displayName) === '') {
                    $displayName = $model->displayNameForUser($account);
                    $_SESSION['display_name'] = $displayName;
                }
                $id = $model->submit(
                    $account, (int) $project, (int) $conversation, $message,
                    hash('sha256', session_id()), $displayName
                );
                if ($ajax) {
                    $this->json(202, ['ok' => true, 'id' => $id]);
                    return;
                }
                header('Location: /chat');
                exit;
            } catch (InvalidArgumentException | RuntimeException $e) {
                if ($ajax) {
                    $this->json(422, ['ok' => false, 'error' => $e->getMessage()]);
                    return;
                }
            } catch (Throwable $e) {
                error_log('Digit Chat enqueue: ' . $e->getMessage());
                if ($ajax) {
                    $this->json(503, ['ok' => false, 'error' => 'Chat delivery unavailable.']);
                    return;
                }
            }
        }
        $projects = $model->projects($account);
        [$project, $conversation] = $this->selected();
        $conversations = [];
        $history = null;
        try {
            if ($project > 0) {
                $conversations = $model->conversations($account, $project);
                if ($conversation > 0) {
                    $history = $model->history($account, $project, $conversation);
                }
            }
        } catch (Throwable $e) {
            $project = $conversation = 0;
            $this->remember(0, 0);
        }
        header('Content-Type: text/html; charset=utf-8');
        $this->view('index', compact('projects', 'project', 'conversation', 'conversations', 'history'));
    }

    public function workspace(array $params = []): void
    {
        $this->require_admin(7);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(405, ['ok' => false, 'error' => 'Method not allowed.']);
            return;
        }
        $this->require_csrf();
        try {
            $model = $this->model('chat_model');
            if ($model->databaseState() !== 'current') {
                throw new RuntimeException('Chat database unavailable.');
            }
            $account = $this->account();
            $action = $_POST['action'] ?? null;
            if (!is_string($action)) {
                throw new InvalidArgumentException('Invalid action.');
            }
            if ($action === 'create_project') {
                $title = $_POST['title'] ?? null;
                if (!is_string($title)) {
                    throw new InvalidArgumentException('Invalid project title.');
                }
                $project = $model->createProject($account, $title);
                $this->remember($project, 0);
                $this->json(200, ['ok' => true, 'projects' => $model->projects($account),
                    'project' => $project, 'conversation' => 0, 'conversations' => [], 'history' => null]);
                return;
            }
            $project = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]);
            if (!$project) {
                throw new InvalidArgumentException('Select a project.');
            }
            if ($action === 'open_project') {
                $conversations = $model->conversations($account, (int) $project);
                $this->remember((int) $project, 0);
                $this->json(200, ['ok' => true, 'project' => $project, 'conversation' => 0,
                    'conversations' => $conversations, 'history' => null]);
                return;
            }
            if ($action === 'assign_repository') {
                $conversation = filter_var(
                    $_POST['conversation_id'] ?? null,
                    FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]
                );
                $repository = $_POST['repository'] ?? null;
                if (!$conversation || !is_string($repository)) {
                    throw new InvalidArgumentException('Select a chat and repository.');
                }
                $model->assignRepository(
                    $account, (int) $project, (int) $conversation, $repository
                );
                $history = $model->history($account, (int) $project, (int) $conversation);
                $this->remember((int) $project, (int) $conversation);
                $this->json(200, [
                    'ok' => true, 'project' => $project, 'conversation' => $conversation,
                    'conversations' => $model->conversations($account, (int) $project),
                    'history' => $history
                ]);
                return;
            }
            if ($action === 'create_conversation') {
                $title = $_POST['title'] ?? null;
                if (!is_string($title)) {
                    throw new InvalidArgumentException('Invalid conversation title.');
                }
                $conversation = $model->createConversation($account, (int) $project, $title);
            } elseif ($action === 'open_conversation') {
                $conversation = filter_var($_POST['conversation_id'] ?? null, FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]);
                if (!$conversation) {
                    throw new InvalidArgumentException('Select a conversation.');
                }
            } else {
                throw new InvalidArgumentException('Unsupported workspace action.');
            }
            $history = $model->history($account, (int) $project, (int) $conversation);
            $this->remember((int) $project, (int) $conversation);
            $this->json(200, ['ok' => true, 'project' => $project,
                'conversation' => $conversation,
                'conversations' => $model->conversations($account, (int) $project),
                'history' => $history]);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->json(404, ['ok' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Chat workspace: ' . $e->getMessage());
            $this->json(503, ['ok' => false, 'error' => 'Workspace unavailable.']);
        }
    }

    public function status(array $params = []): void
    {
        $this->require_admin(7);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(405, ['ok' => false, 'error' => 'Method not allowed.']);
            return;
        }
        $this->require_csrf();
        try {
            $state = $this->model('chat_model')->status(
                (string) ($_POST['id'] ?? ''),
                hash('sha256', session_id()),
                $this->account()
            );
            if ($state === null) {
                $this->json(404, ['ok' => false, 'error' => 'Message not found.']);
                return;
            }
            $this->json(200, ['ok' => true] + $state);
        } catch (Throwable $e) {
            error_log('Digit Chat status: ' . $e->getMessage());
            $this->json(503, ['ok' => false, 'error' => 'Chat status unavailable.']);
        }
    }

    public function transport(array $params = []): void
    {
        // Digit's existing outbound HTTP transport. No browser session applies.
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
            if ($model->databaseState() !== 'current') {
                $this->json(503, ['error' => 'Chat schema unavailable.']);
                return;
            }
            if ($payload['type'] === 'poll') {
                $job = $model->pending();
                if ($job === null) {
                    http_response_code(204);
                    header('Cache-Control: no-store');
                    return;
                }
                $this->json(200, $job);
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
