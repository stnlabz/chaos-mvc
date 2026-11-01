<?php
declare(strict_types=1);

/**
 * Disposable module lifecycle fixture.
 * No model, database schema, migrations, or persistent state.
 */
final class lifecycle_test extends controller
{
    public function index(array $params = []): void
    {
        $this->view('index');
    }

    public function admin(array $params = []): void
    {
        $this->require_admin(7);
        $this->view('admin/index');
    }
}
