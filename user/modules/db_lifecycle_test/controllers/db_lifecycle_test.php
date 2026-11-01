<?php
declare(strict_types=1);

/** Disposable DB qualification fixture. Never writes to Core-owned tables. */
final class db_lifecycle_test extends controller
{
    public function index(array $params = []): void
    {
        $this->view('index');
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(9);
        $model = $this->model('db_lifecycle_test_model');
        $message = null;
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            try {
                $action = (string) ($_POST['action'] ?? '');
                $state = $model->databaseState();
                if ($action === 'install_sql' && $state === 'missing') {
                    $model->installSchema();
                    $message = 'Disposable database schema and reference row installed.';
                } elseif ($action === 'update_sql' && $state === 'update') {
                    $model->updateSchema();
                    $message = 'Module schema updated to 1.0.1.';
                } elseif ($action === 'delete_data' && $state === 'current') {
                    $model->deleteData();
                    $message = 'Module data deleted; schema preserved.';
                } elseif ($action === 'reset_data' && $state === 'current') {
                    $model->resetData();
                    $message = 'Canonical reference data restored.';
                } else {
                    throw new RuntimeException('Invalid database lifecycle action for the current state.');
                }
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $this->view('admin/index', [
            'state' => $model->databaseState(),
            'schema_version' => $model->targetVersion(),
            'has_migration_column' => $model->hasMigrationColumn(),
            'record_count' => $model->recordCount(),
            'reference_found' => $model->referenceFound(),
            'message' => $message,
            'error' => $error,
        ]);
    }
}
