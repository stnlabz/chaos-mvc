<?php require_once APPROOT . '/views/inc/head.php';?>
<h1>Chaos MVC Installer</h1>

<?php if (!empty($data['error'])): ?>
<p style="color:red"><?= htmlspecialchars($data['error']) ?></p>
<?php endif; ?>

<form method="post">

<h2>Database</h2>

<input name="db_host" placeholder="Database Host"><br>
<input name="db_user" placeholder="Database User"><br>
<input name="db_pass" placeholder="Database Password"><br>
<input name="db_name" placeholder="Database Name"><br>

<h2>Administrator</h2>

<input name="admin_user" placeholder="Admin Username"><br>
<input name="admin_email" placeholder="Admin Email"><br>
<input type="password" name="admin_pass" placeholder="Admin Password"><br>

<button type="submit">Install Chaos MVC</button>

</form>

<?php require_once APPROOT . '/views/inc/foot.php';?>
