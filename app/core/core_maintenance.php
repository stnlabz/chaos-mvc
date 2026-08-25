<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_maintenance
{
    private string $stateDirectory;
    private string $statePath;

    public function __construct(string $stateDirectory)
    {
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
        $this->statePath = $this->stateDirectory . '/maintenance.json';
    }

    public function activate(string $operationId, string $phase): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $operationId) || !preg_match('/^[a-z_]+$/', $phase)) {
            return false;
        }

        if (!is_dir($this->stateDirectory) && !@mkdir($this->stateDirectory, 0750, true)) {
            return false;
        }

        return $this->write([
            'active' => true,
            'operation_id' => $operationId,
            'phase' => $phase,
            'started_at' => gmdate('c'),
            'updated_at' => gmdate('c')
        ]);
    }

    public function update(string $operationId, string $phase): bool
    {
        $state = $this->read();

        if (
            ($state['active'] ?? false) !== true ||
            ($state['operation_id'] ?? null) !== $operationId ||
            !preg_match('/^[a-z_]+$/', $phase)
        ) {
            return false;
        }

        $state['phase'] = $phase;
        $state['updated_at'] = gmdate('c');

        return $this->write($state);
    }

    public function deactivate(string $operationId): bool
    {
        $state = $this->read();

        if (($state['operation_id'] ?? null) !== $operationId) {
            return false;
        }

        return !is_file($this->statePath) || @unlink($this->statePath);
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        if (!is_file($this->statePath)) {
            return ['active' => false];
        }

        $state = json_decode((string) file_get_contents($this->statePath), true);

        return is_array($state) ? $state : [
            'active' => true,
            'phase' => 'invalid_maintenance_state',
            'recovery_required' => true
        ];
    }

    /**
     * @param array<string, mixed> $session
     */
    public function shouldBlock(array $session): bool
    {
        return ($this->read()['active'] ?? false) === true
            && (int) ($session['user_level'] ?? 0) < 9;
    }

    /**
     * End a non-administrator web request when Core maintenance is active.
     *
     * @param array<string, mixed> $session
     */
    public function enforceWebRequest(array $session): void
    {
        if (PHP_SAPI === 'cli' || !$this->shouldBlock($session)) {
            return;
        }

        http_response_code(503);
        header('Retry-After: 600');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8">'
            . '<title>Chaos MVC Maintenance</title>'
            . '<main><h1>Core maintenance in progress</h1>'
            . '<p>Chaos MVC is being updated. Please try again shortly.</p></main></html>';
        exit;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function write(array $state): bool
    {
        $temporary = $this->statePath . '.tmp.' . bin2hex(random_bytes(6));
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($temporary, $encoded . PHP_EOL, LOCK_EX) === false) {
            @unlink($temporary);
            return false;
        }

        if (!@rename($temporary, $this->statePath)) {
            @unlink($temporary);
            return false;
        }

        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
