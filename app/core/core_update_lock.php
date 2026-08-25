<?php

declare(strict_types=1);

/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
final class core_update_lock
{
    private string $stateDirectory;
    private string $lockPath;

    public function __construct(string $stateDirectory)
    {
        $this->stateDirectory = rtrim($stateDirectory, '/\\');
        $this->lockPath = $this->stateDirectory . '/core.lock';
    }

    /**
     * Atomically acquire the Core-only update lock.
     *
     * @return array<string, mixed>
     */
    public function acquire(string $installedVersion, string $targetVersion): array
    {
        $this->prepareStateDirectory();
        $operationId = bin2hex(random_bytes(16));
        $handle = @fopen($this->lockPath, 'x');

        if ($handle === false) {
            return [
                'success' => false,
                'outcome' => 'update_in_progress',
                'state' => $this->read()
            ];
        }

        $state = [
            'operation_id' => $operationId,
            'installed_version' => $installedVersion,
            'target_version' => $targetVersion,
            'started_at' => gmdate('c'),
            'phase' => 'locked'
        ];
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || fwrite($handle, $encoded . PHP_EOL) === false) {
            fclose($handle);
            @unlink($this->lockPath);
            throw new RuntimeException('Core update lock state could not be written.');
        }

        fflush($handle);
        fclose($handle);

        return ['success' => true, 'outcome' => 'locked', 'state' => $state];
    }

    /**
     * Advance lock state only when the caller owns the operation.
     */
    public function updatePhase(string $operationId, string $phase): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $operationId) || !preg_match('/^[a-z_]+$/', $phase)) {
            return false;
        }

        $state = $this->read();

        if (($state['operation_id'] ?? null) !== $operationId) {
            return false;
        }

        $state['phase'] = $phase;
        $state['updated_at'] = gmdate('c');

        return $this->replaceState($state);
    }

    /**
     * Release the lock only when the caller owns the operation.
     */
    public function release(string $operationId): bool
    {
        $state = $this->read();

        if (($state['operation_id'] ?? null) !== $operationId) {
            return false;
        }

        return @unlink($this->lockPath);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        if (!is_file($this->lockPath)) {
            return null;
        }

        $raw = @file_get_contents($this->lockPath);
        $state = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($state) ? $state : [
            'phase' => 'invalid_lock_state',
            'recovery_required' => true
        ];
    }

    private function prepareStateDirectory(): void
    {
        if (!is_dir($this->stateDirectory) && !@mkdir($this->stateDirectory, 0750, true)) {
            throw new RuntimeException('Core update state directory could not be created.');
        }

        if (!is_writable($this->stateDirectory)) {
            throw new RuntimeException('Core update state directory is not writable.');
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function replaceState(array $state): bool
    {
        $temporaryPath = $this->lockPath . '.tmp.' . bin2hex(random_bytes(6));
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false || file_put_contents($temporaryPath, $encoded . PHP_EOL, LOCK_EX) === false) {
            @unlink($temporaryPath);
            return false;
        }

        if (!@rename($temporaryPath, $this->lockPath)) {
            @unlink($temporaryPath);
            return false;
        }

        return true;
    }
}
/* [End AI:GPT-5.6 Sol] */
