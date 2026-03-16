<?php require APPROOT . '/views/inc/head.php'; ?>

<h1>Chaos MVC Example Module</h1>

<p>This page demonstrates the MVC flow inside Chaos MVC.</p>

<h2>Message from the Database</h2>

<p><?= htmlspecialchars($data['message']); ?></p>

<hr>

<h3>MVC Flow</h3>

<pre>
Controller → <code>app/controllers/example.php</code>
Model → <code>app/models/example_model.php</code>
View → <code>public/example/index.php</code>
</pre>

<?php require APPROOT . '/views/inc/foot.php'; ?>
