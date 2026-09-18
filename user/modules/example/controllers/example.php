<?php
declare(strict_types=1);

// path: /user/modules/example/controllers/example.php

/**
 * Example Controller
 *
 * Canonical reference controller for a current ChAoS MVC user Module.
 */

final class example extends controller
{
    private const CRUD_ACTIONS = [
        'create',
        'update',
        'delete',
    ];

    private const LIFECYCLE_ACTIONS = [
        'install_sql',
        'update_sql',
        'delete_data',
        'reset_data',
    ];

    public function index(array $params = []): void
    {
        $model = $this->model('example_model');

        $this->view('index', [
            'module' => $model->getModuleInformation(),
        ]);
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(7);

        $model = $this->model('example_model');
        $state = $model->databaseState();
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $action = trim((string) ($_POST['action'] ?? ''));

            try {
                if (in_array($action, self::LIFECYCLE_ACTIONS, true)) {
                    $this->handleLifecycleAction($model, $state, $action);
                }

                if ($state !== 'current') {
                    throw new RuntimeException(
                        'Complete the database lifecycle action first.'
                    );
                }

                if (!in_array($action, self::CRUD_ACTIONS, true)) {
                    throw new InvalidArgumentException(
                        'Invalid Example administration action.'
                    );
                }

                if ($action === 'create') {
                    $model->createRecord($_POST);
                    $this->redirectAdmin('created');
                }

                if ($action === 'update') {
                    $model->updateRecord($_POST);
                    $this->redirectAdmin('updated');
                }

                $model->deleteRecord((int) ($_POST['id'] ?? 0));
                $this->redirectAdmin('deleted');
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = 'The Example operation could not be completed.';
            }
        }

        $state = $model->databaseState();

        $this->view('admin/index', [
            'module' => $model->getModuleInformation(),
            'database_state' => $state,
            'records' => $state === 'current'
                ? $model->getRecords()
                : [],
            'message' => $this->statusMessage(
                trim((string) ($_GET['status'] ?? ''))
            ),
            'error' => $error,
        ]);
    }

    private function handleLifecycleAction(
        example_model $model,
        string $state,
        string $action
    ): never {
        if ($action === 'install_sql') {
            if ($state !== 'missing') {
                throw new RuntimeException('Schema is already installed.');
            }

            $model->installSchema();
            $this->redirectAdmin('installed');
        }

        if ($action === 'update_sql') {
            if ($state !== 'update') {
                throw new RuntimeException('No schema update is pending.');
            }

            $model->updateSchema();
            $this->redirectAdmin('schema_updated');
        }

        if ($state !== 'current') {
            throw new RuntimeException(
                'Complete the database lifecycle action first.'
            );
        }

        if ($action === 'delete_data') {
            $model->deleteData();
            $this->redirectAdmin('data_deleted');
        }

        if ($action === 'reset_data') {
            $model->resetData();
            $this->redirectAdmin('data_reset');
        }

        throw new InvalidArgumentException(
            'Invalid Example lifecycle action.'
        );
    }

    private function statusMessage(string $status): ?string
    {
        $messages = [
            'installed' => 'Example schema installed and reference data restored.',
            'schema_updated' => 'Example schema updated.',
            'data_deleted' => 'Example module data deleted. Installed schema was preserved.',
            'data_reset' => 'Example data reset to its canonical reference state.',
            'created' => 'Example record created.',
            'updated' => 'Example record updated.',
            'deleted' => 'Example record deleted.',
        ];

        return $messages[$status] ?? null;
    }

    private function redirectAdmin(string $status): never
    {
        header(
            'Location: /admin/example?status='
            . rawurlencode($status)
        );
        exit;
    }
}