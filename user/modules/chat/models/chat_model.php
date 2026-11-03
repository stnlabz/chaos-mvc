<?php
declare(strict_types=1);

/**
 * Digit Chat storage. Every user-facing lookup is scoped by account, project
 * AND conversation. No implicit cross-project or cross-chat context retrieval.
 */
final class chat_model extends model
{
    private const MAX_REQUEST = 2047;
    private const MAX_REPLY = 511;
    private const DELIVERY_TTL = 90;

    public function databaseState(): string
    {
        foreach (['chat_projects', 'chat_conversations', 'chat_messages', 'chat_deliveries'] as $table) {
            $row = $this->fetch(
                'SELECT COUNT(*) AS n FROM information_schema.tables ' .
                'WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            if ((int) ($row['n'] ?? 0) !== 1) {
                return 'missing';
            }
        }
        return 'current';
    }

    /** Admin-only explicit installation; never called from visitor routes. */
    public function installSchema(): void
    {
        if ($this->databaseState() !== 'missing') {
            throw new RuntimeException('Chat database already installed.');
        }
        $path = USERROOT . '/modules/chat/sql/schema.sql';
        $sql = file_get_contents($path);
        if (!is_string($sql) || $sql === '') {
            throw new RuntimeException('Chat SQL file unavailable.');
        }
        foreach (explode(';', $sql) as $statement) {
            // The schema uses only straightforward DDL without semicolons in literals.
            $statement = trim($statement);
            if ($statement !== '') {
                $this->db->exec($statement);
            }
        }
        if ($this->databaseState() !== 'current') {
            throw new RuntimeException('Chat SQL installation incomplete.');
        }
    }

    public function displayNameForUser(int $userId): string
    {
        $row = $this->fetch(
            'SELECT display_name, username FROM accounts WHERE id = ? LIMIT 1',
            [$userId]
        );
        if (!is_array($row)) {
            throw new RuntimeException('Account unavailable.');
        }
        return trim((string) ($row['display_name'] ?? '')) ?: (string) $row['username'];
    }

    private function validTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || strlen($title) > 120 ||
            preg_match('//u', $title) !== 1 ||
            preg_match('/[\x00-\x1f\x7f]/', $title) === 1) {
            throw new InvalidArgumentException('Enter a title up to 120 bytes.');
        }
        return $title;
    }

    private function validMessage(string $message): void
    {
        if ($message === '' || strlen($message) > self::MAX_REQUEST ||
            preg_match('//u', $message) !== 1 ||
            preg_match('/[\x00-\x1f\x7f]/', $message) === 1) {
            throw new InvalidArgumentException('Enter a single-line UTF-8 message (1–2047 bytes).');
        }
    }

    public function projects(int $account): array
    {
        return $this->fetchAll(
            'SELECT id, title FROM chat_projects WHERE account_id = ? ORDER BY id DESC',
            [$account]
        );
    }

    public function createProject(int $account, string $title): int
    {
        $title = $this->validTitle($title);
        $this->query(
            'INSERT INTO chat_projects (account_id, title, context_text) VALUES (?, ?, ?)',
            [$account, $title, '']
        );
        return (int) $this->db->lastInsertId();
    }

    private function project(int $account, int $project): array
    {
        $row = $this->fetch(
            'SELECT id, title, context_text FROM chat_projects WHERE account_id = ? AND id = ?',
            [$account, $project]
        );
        if (!is_array($row)) {
            throw new RuntimeException('Project not found or access denied.');
        }
        return $row;
    }

    public function conversations(int $account, int $project): array
    {
        $this->project($account, $project);
        return $this->fetchAll(
            'SELECT id, title FROM chat_conversations WHERE account_id = ? ' .
            'AND project_id = ? ORDER BY updated_at DESC, id DESC',
            [$account, $project]
        );
    }

    public function createConversation(int $account, int $project, string $title): int
    {
        $title = $this->validTitle($title);
        $this->project($account, $project);
        $this->query(
            'INSERT INTO chat_conversations (account_id, project_id, title) VALUES (?, ?, ?)',
            [$account, $project, $title]
        );
        return (int) $this->db->lastInsertId();
    }

    private function scope(int $account, int $project, int $conversation): array
    {
        $row = $this->fetch(
            'SELECT c.id, c.title, p.title AS project_title, p.context_text ' .
            'FROM chat_conversations c INNER JOIN chat_projects p ' .
            'ON p.id = c.project_id AND p.account_id = c.account_id ' .
            'WHERE c.account_id = ? AND c.project_id = ? AND c.id = ?',
            [$account, $project, $conversation]
        );
        if (!is_array($row)) {
            throw new RuntimeException('Conversation not found or access denied.');
        }
        return $row;
    }

    public function history(int $account, int $project, int $conversation): array
    {
        $scope = $this->scope($account, $project, $conversation);
        $rows = $this->fetchAll(
            'SELECT id, role, content, created_at FROM (' .
            ' SELECT id, role, content, created_at FROM chat_messages ' .
            ' WHERE account_id = ? AND project_id = ? AND conversation_id = ? ' .
            ' ORDER BY id DESC LIMIT 100' .
            ') recent ORDER BY id ASC',
            [$account, $project, $conversation]
        );
        return ['title' => $scope['title'], 'project_title' => $scope['project_title'], 'messages' => $rows];
    }

    /** Clip on Unicode character boundaries; native Web Transport expects valid UTF-8. */
    private function clipUtf8(string $text, int $bytes): string
    {
        if (strlen($text) <= $bytes) {
            return $text;
        }
        $out = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (strlen($out) + strlen($char) > $bytes) {
                break;
            }
            $out .= $char;
        }
        return $out;
    }

    /** Construct the only context Digit may see: this exact project + conversation. */
    private function contextText(int $account, int $project, int $conversation, string $message): string
    {
        $scope = $this->scope($account, $project, $conversation);
        $recent = $this->fetchAll(
            'SELECT role, content FROM chat_messages WHERE account_id = ? ' .
            'AND project_id = ? AND conversation_id = ? ORDER BY id DESC LIMIT 8',
            [$account, $project, $conversation]
        );
        $entries = [];
        foreach (array_reverse($recent) as $row) {
            $line = str_replace(["\r", "\n", "\t", '|'], ' ', (string) $row['content']);
            $entries[] = ($row['role'] === 'digit' ? 'Digit: ' : 'User: ') .
                $this->clipUtf8($line, 180);
        }
        $context = trim((string) $scope['context_text']);
        $prefix = 'Project ' . $scope['project_title'] . '; conversation ' .
            $scope['title'] . '. ';
        if ($context !== '') {
            $prefix .= 'Project notes: ' . $this->clipUtf8(str_replace(["\r", "\n"], ' ', $context), 250) . '. ';
        }
        $history = implode(' | ', $entries);
        if ($history === '') {
            // Preserve all ordinary chat and explicit slash commands for new threads.
            return $message;
        }
        // Never alter slash/module commands, identity questions or routing metadata.
        if (str_starts_with($message, '/') ||
            preg_match('/^(who am i|what is my name|do you know my name)\s*[?.!]*$/i', $message)) {
            return $message;
        }
        $budget = self::MAX_REQUEST - strlen($message) - 180;
        if ($budget < 64) {
            return $message;
        }
        $history = $this->clipUtf8($prefix . $history, min($budget, 1100));
        $isGreeting = preg_match('/^(hi|hello|hey)[!., ]*$/i', $message) === 1;
        if ($isGreeting) {
            // Native Ask module recognizes this bounded, isolated resume envelope.
            return '[resume] ' . $history . ' | User: ' . $message;
        }
        return '[conversation] ' . $history . ' | Current user: ' . $message;
    }

    public function submit(
        int $account, int $project, int $conversation,
        string $message, string $sessionHash, string $displayName
    ): string {
        $this->validMessage($message);
        $displayName = trim($displayName);
        if ($displayName === '' || strlen($displayName) > 127 ||
            preg_match('//u', $displayName) !== 1 ||
            preg_match('/[\x00-\x1f\x7f]/', $displayName) === 1) {
            throw new InvalidArgumentException('Invalid account display name.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $sessionHash)) {
            throw new InvalidArgumentException('Invalid session.');
        }
        $transport = $this->contextText($account, $project, $conversation, $message);
        if (strlen($transport) > self::MAX_REQUEST) {
            $transport = $message;
        }
        $id = bin2hex(random_bytes(16));
        $this->db->beginTransaction();
        try {
            $this->query(
                'INSERT INTO chat_deliveries ' .
                '(id, account_id, project_id, conversation_id, session_hash, ' .
                'display_name, message, transport_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $account, $project, $conversation, $sessionHash, $displayName, $message, $transport]
            );
            $this->query(
                'INSERT INTO chat_messages (account_id, project_id, conversation_id, role, content) ' .
                'VALUES (?, ?, ?, ?, ?)',
                [$account, $project, $conversation, 'user', $message]
            );
            $this->query(
                'UPDATE chat_conversations SET updated_at = NOW() ' .
                'WHERE id = ? AND account_id = ? AND project_id = ?',
                [$conversation, $account, $project]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }

    public function pending(): ?array
    {
        $this->db->beginTransaction();
        try {
            $this->query(
                'UPDATE chat_deliveries SET state = ? WHERE state IN (?, ?) ' .
                'AND created_at < DATE_SUB(NOW(), INTERVAL 90 SECOND)',
                ['complete', 'queued', 'processing']
            );
            $row = $this->fetch(
                'SELECT id, transport_text, display_name FROM chat_deliveries ' .
                'WHERE state = ? ORDER BY created_at ASC, id ASC LIMIT 1 FOR UPDATE',
                ['queued']
            );
            if (!is_array($row)) {
                $this->db->commit();
                return null;
            }
            $this->query('UPDATE chat_deliveries SET state = ? WHERE id = ?', ['processing', $row['id']]);
            $this->db->commit();
            return [
                'id' => $row['id'],
                'message' => $row['transport_text'],
                'display_name' => $row['display_name']
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function receive(string $id, string $status, string $message): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id) ||
            !in_array($status, ['ok', 'error'], true) ||
            strlen($message) > self::MAX_REPLY ||
            preg_match('//u', $message) !== 1) {
            throw new InvalidArgumentException('Invalid transport reply.');
        }
        $this->db->beginTransaction();
        try {
            $row = $this->fetch(
                'SELECT account_id, project_id, conversation_id, state FROM chat_deliveries ' .
                'WHERE id = ? FOR UPDATE', [$id]
            );
            if (!is_array($row) || $row['state'] !== 'processing') {
                $this->db->commit();
                return false;
            }
            $this->query(
                'UPDATE chat_deliveries SET state = ?, result = ?, reply_text = ? WHERE id = ?',
                ['complete', $status, $message, $id]
            );
            if ($status === 'ok') {
                $this->query(
                    'INSERT INTO chat_messages (account_id, project_id, conversation_id, role, content) ' .
                    'VALUES (?, ?, ?, ?, ?)',
                    [$row['account_id'], $row['project_id'], $row['conversation_id'], 'digit', $message]
                );
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function status(string $id, string $sessionHash, int $account): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            return null;
        }
        $row = $this->fetch(
            'SELECT d.state, d.result AS status, d.reply_text AS reply ' .
            'FROM chat_deliveries d JOIN chat_conversations c ' .
            'ON c.id=d.conversation_id AND c.account_id=d.account_id AND c.project_id=d.project_id ' .
            'WHERE d.id = ? AND d.session_hash = ? AND d.account_id = ? AND c.account_id = ?',
            [$id, $sessionHash, $account, $account]
        );
        return is_array($row) ? $row : null;
    }
}
