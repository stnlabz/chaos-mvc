<?php
if (!theme::render('head', get_defined_vars())) require APPROOT . '/views/inc/head.php';
?>
<div class="container my-3">
    <h1>Database Lifecycle Test</h1>
    <p>Disposable ChAoS MVC 1.2.2 database qualification fixture.</p>
    <p>All database controls are restricted to Level 9 administration.</p>
</div>
<?php
if (!theme::render('foot', get_defined_vars())) require APPROOT . '/views/inc/foot.php';
