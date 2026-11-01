<?php
declare(strict_types=1);

if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
?>
<div class="container my-3">
    <h1>Lifecycle Test Administration</h1>
    <p>Administrative route operational. Fixture version: 1.0.0.</p>
    <p>No database, migration, or data-lifecycle actions exist in this module.</p>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}
