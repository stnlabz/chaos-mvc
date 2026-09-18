<?php
// path: /user/modules/example/views/index.php

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}

$module = is_array($data['module'] ?? null)
    ? $data['module']
    : [];
?>

<p>
    <small>
        <a href="/">Home</a> &gt;&gt;
        <strong>Example Module</strong>
    </small>
</p>

<div class="container my-3">
    <h1>ChAoS MVC Example Module</h1>

    <p>
        The Example Module is the reference implementation for developing
        user Modules for ChAoS MVC. It demonstrates the current Module
        architecture, routing, MVC structure, database handling, Module and
        Data Lifecycles, complete CRUD operations, administrative actions,
        CSRF-protected state changes, and deterministic status handling.
    </p>

    <p>
        Example is intentionally working code rather than a decorative
        demonstration. Developers can inspect its controller, model, views,
        SQL lifecycle files, Module manifest, and documentation to see how
        these capabilities are implemented without modifying ChAoS MVC Core.
    </p>

    <p>
        The public page remains intentionally simple. The functional
        demonstrations are available through the Module's administration
        interface so the implementation can be exercised and inspected
        without turning the public Example route into an application.
    </p>

    <p>
        Module:
        <strong><?= htmlspecialchars(
            (string) ($module['name'] ?? 'Example'),
            ENT_QUOTES,
            'UTF-8'
        ); ?></strong>
        &middot;
        Version
        <?= htmlspecialchars(
            (string) ($module['version'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </p>
</div>

<?php

if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
