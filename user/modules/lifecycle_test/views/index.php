<?php
declare(strict_types=1);

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<div class="container my-3">
    <h1>Lifecycle Test Module</h1>
    <p>Public route operational. Fixture version: 1.0.0.</p>
    <p>This disposable module creates no database tables or persistent data.</p>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
