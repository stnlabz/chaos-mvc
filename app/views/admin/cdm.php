<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);
$modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
$overview = is_array($data['overview'] ?? null) ? $data['overview'] : [];
$capabilities = is_array($data['capabilities'] ?? null)
    ? $data['capabilities']
    : null;
$moduleReport = is_array($data['module_report'] ?? null)
    ? $data['module_report']
    : null;
$selectedModule = is_string($data['selected_module'] ?? null)
    ? $data['selected_module']
    : '';
$statusClass = static function (string $status): string {
    return match ($status) {
        'DEMONSTRATED' => 'cdm-demonstrated',
        'FAILED', 'CONFIRMED' => 'cdm-failed',
        'UNVERIFIED', 'POTENTIAL MISMATCH' => 'cdm-unverified',
        default => 'cdm-observed',
    };
};
?>
<main class="cdm-admin">
    <header>
        <p><small><a href="/admin">Admin</a> &gt;&gt; <strong>CDM</strong></small></p>
        <h1>CDM — Diagnostic Service</h1>
        <p>Level-9, evidence-classified inspection of the active ChAoS runtime.</p>
    </header>

    <?php if (!empty($data['error'])): ?>
        <div role="alert"><strong>Diagnostic error:</strong> <?= $escape($data['error']); ?></div>
    <?php endif; ?>

    <nav aria-label="CDM diagnostic areas">
        <a href="#overview">Overview</a> |
        <a href="#capabilities">Capability probes</a> |
        <a href="#modules">Module inspection</a>
    </nav>

    <section id="overview">
        <h2>Passive Overview</h2>
        <p>Observed values do not establish operational capability.</p>
        <?php foreach ($overview as $section => $checks): ?>
            <details>
                <summary><strong><?= $escape($section); ?></strong></summary>
                <table>
                    <thead><tr><th>Status</th><th>Check</th><th>Evidence</th></tr></thead>
                    <tbody>
                    <?php foreach ($checks as $check): ?>
                        <tr>
                            <td class="<?= $escape($statusClass((string) $check['status'])); ?>"><?= $escape($check['status']); ?></td>
                            <td><?= $escape($check['summary']); ?></td>
                            <td>
                                <?= $escape($check['evidence']); ?>
                                <?php if (!empty($check['action'])): ?>
                                    <br><small><?= $escape($check['action']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endforeach; ?>
    </section>

    <section id="capabilities">
        <h2>Controlled Capability Probes</h2>
        <p>Runs bounded filesystem, logging, and database operations. Temporary filesystem artifacts are cleaned up.</p>
        <form action="/admin/cdm" method="post">
            <?= $this->csrf_field(); ?>
            <input type="hidden" name="action" value="capabilities">
            <button type="submit">Run capability probes</button>
        </form>

        <?php if ($capabilities !== null): ?>
            <?php foreach ($capabilities as $section => $checks): ?>
                <h3><?= $escape($section); ?></h3>
                <?php foreach ($checks as $check): ?>
                    <article>
                        <h4><?= $escape($check['summary']); ?></h4>
                        <p><strong class="<?= $escape($statusClass((string) $check['status'])); ?>"><?= $escape($check['status']); ?></strong></p>
                        <p><?= $escape($check['evidence']); ?></p>
                        <?php if (!empty($check['action'])): ?>
                            <p><small><?= $escape($check['action']); ?></small></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section id="modules">
        <h2>Module Inspection</h2>
        <p>Reads bounded metadata and declared source files without executing module PHP.</p>
        <form action="/admin/cdm" method="post">
            <?= $this->csrf_field(); ?>
            <input type="hidden" name="action" value="module">
            <label for="cdm-module">Installed module</label>
            <select id="cdm-module" name="module" required>
                <?php foreach ($modules as $name): ?>
                    <option value="<?= $escape($name); ?>" <?= $name === $selectedModule ? 'selected' : ''; ?>><?= $escape($name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" <?= $modules === [] ? 'disabled' : ''; ?>>Inspect module</button>
        </form>

        <?php if ($moduleReport !== null): ?>
            <h3>Module: <?= $escape($moduleReport['module']); ?></h3>
            <p><?= $escape($moduleReport['note']); ?></p>
            <p><strong>Files inspected:</strong> <?= $escape(implode(', ', $moduleReport['inspected'])); ?></p>

            <?php if ($moduleReport['findings'] === []): ?>
                <p>No findings from the implemented checks. This is not proof of correctness.</p>
            <?php endif; ?>

            <?php foreach ($moduleReport['findings'] as $finding): ?>
                <article>
                    <h4><?= $escape($finding['summary']); ?></h4>
                    <p><strong><?= $escape($finding['status']); ?></strong> — <code><?= $escape($finding['code']); ?></code></p>
                    <p><strong>Evidence:</strong> <?= $escape($finding['evidence']); ?></p>
                    <p><strong>Recommended repair:</strong> <?= $escape($finding['repair']); ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
