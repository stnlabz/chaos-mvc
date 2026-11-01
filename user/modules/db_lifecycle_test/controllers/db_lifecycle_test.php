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
                if (($_POST['action'] ?? '') !== 'install_sql' || $model->databaseState() !== 'missing') {
                    throw new RuntimeException('Invalid database lifecycle action.');
                }
                $model->installSchema();
                $message = 'Disposable database schema created and reference row inserted.';
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
