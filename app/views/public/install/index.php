<?php

/**
 * Chaos MVC Installer View
 *
 * Displays the initial database and administrator configuration form.
 *
 * Path: /app/views/public/install/index.php
 *
 * @package Chaos MVC
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 02:19:00 UTC] */

require_once APPROOT . '/views/inc/head.php';

$preflight = is_array($data['preflight'] ?? null) ? $data['preflight'] : [];
if ($preflight === []) {
    $preflight = installer_preflight::qualify();
}
$environment = is_array($preflight['environment'] ?? null) ? $preflight['environment'] : [];
$preflightPassed = ($preflight['passed'] ?? false) === true;
?>

<div class="container py-4">
    <h1>Chaos MVC Installer</h1>

    <p>
        ChAoS must positively identify and qualify this environment before
        installation can make any changes.
    </p>

    <section class="mb-4" aria-labelledby="installer-environment">
        <h2 id="installer-environment">Environment</h2>
        <dl class="row">
            <dt class="col-sm-4">Operating System</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['operating_system'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-sm-4">Web Server</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['web_server'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-sm-4">Server Software</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['server_software'] ?? 'Unavailable'), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-sm-4">PHP</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['php_version'] ?? PHP_VERSION), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-sm-4">PHP SAPI</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['php_sapi'] ?? PHP_SAPI), ENT_QUOTES, 'UTF-8'); ?></dd>
            <dt class="col-sm-4">Runtime User</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($environment['runtime_user'] ?? 'Unavailable'), ENT_QUOTES, 'UTF-8'); ?></dd>
        </dl>

        <?php foreach (($preflight['environment_errors'] ?? []) as $environmentError) : ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $environmentError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
    </section>

    <section class="mb-4" aria-labelledby="installer-capabilities">
        <h2 id="installer-capabilities">Capability Qualification</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Result</th><th>Resource</th><th>Required</th><th>Evidence</th></tr></thead>
                <tbody>
                <?php foreach (($preflight['requirements'] ?? []) as $requirement) : ?>
                    <tr>
                        <td><strong><?= !empty($requirement['passed']) ? 'PASS' : 'FAIL'; ?></strong></td>
                        <td><?= htmlspecialchars((string) ($requirement['resource'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($requirement['required'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($requirement['result'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (!empty($data['error'])) : ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars(
                (string) $data['error'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>
    <?php endif; ?>

    <?php if (!$preflightPassed) : ?>
        <div class="alert alert-danger" role="alert">
            <strong>Installation refused.</strong>
            ChAoS could not positively identify and validate this environment.
            Correct the reported failures and run the installer again. No installation changes have been made.
        </div>
    <?php else : ?>
    <form method="post" action="/install">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <section class="mb-4">
            <h2>Database</h2>

            <div class="mb-3">
                <label for="db-host" class="form-label">
                    Database Host
                </label>

                <input
                    id="db-host"
                    class="form-control"
                    type="text"
                    name="db_host"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['db_host'] ?? 'localhost'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="db-user" class="form-label">
                    Database User
                </label>

                <input
                    id="db-user"
                    class="form-control"
                    type="text"
                    name="db_user"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['db_user'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="db-pass" class="form-label">
                    Database Password
                </label>

                <input
                    id="db-pass"
                    class="form-control"
                    type="password"
                    name="db_pass"
                >
            </div>

            <div class="mb-3">
                <label for="db-name" class="form-label">
                    Database Name
                </label>

                <input
                    id="db-name"
                    class="form-control"
                    type="text"
                    name="db_name"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['db_name'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>
        </section>

        <section class="mb-4">
            <h2>Administrator</h2>

            <div class="mb-3">
                <label for="admin-user" class="form-label">
                    Username
                </label>

                <input
                    id="admin-user"
                    class="form-control"
                    type="text"
                    name="admin_user"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['admin_user'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="admin-display-name" class="form-label">
                    Display Name
                </label>

                <input
                    id="admin-display-name"
                    class="form-control"
                    type="text"
                    name="admin_display_name"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['admin_display_name'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="admin-email" class="form-label">
                    Email Address
                </label>

                <input
                    id="admin-email"
                    class="form-control"
                    type="email"
                    name="admin_email"
                    value="<?= htmlspecialchars(
                        (string) ($_POST['admin_email'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="admin-pass" class="form-label">
                    Password
                </label>

                <input
                    id="admin-pass"
                    class="form-control"
                    type="password"
                    name="admin_pass"
                    required
                >
            </div>
        </section>

        <button type="submit" class="btn btn-primary">
            Install Chaos MVC
        </button>
    </form>
    <?php endif; ?>
</div>

<?php
require_once APPROOT . '/views/inc/foot.php';

/* [End AI:GPT-5.6 Sol] */
?>
