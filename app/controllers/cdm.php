<?php
declare(strict_types=1);

final class cdm extends controller
{
    public static $is_core = true;

    public function index(array $params = []): void
    {
        $this->require_admin(9);
        header('Location: /admin/cdm');
        exit;
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(9);
        /** @var cdm_model $model */
        $model = $this->model('cdm_model');
        $modules = $model->modules();
        $overview = $model->overview();
        $capabilities = null;
        $moduleReport = null;
        $selectedModule = null;
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->require_csrf();
            $action = (string) ($_POST['action'] ?? '');
            try {
                if ($action === 'capabilities') {
                    $capabilities = $model->capabilityProbes();
                } elseif ($action === 'module') {
                    $name = trim((string) ($_POST['module'] ?? ''));
                    $selectedModule = $name;
                    $moduleReport = $model->diagnose($name);
                } else {
                    throw new InvalidArgumentException(
                        'Select a supported diagnostic operation.'
                    );
                }
            } catch (InvalidArgumentException | RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        $this->view('admin/cdm', [
            'modules' => $modules,
            'overview' => $overview,
            'capabilities' => $capabilities,
            'module_report' => $moduleReport,
            'selected_module' => $selectedModule,
            'error' => $error,
        ]);
    }
}
