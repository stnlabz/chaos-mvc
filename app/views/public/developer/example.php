<?php
require APPROOT . '/views/inc/head.php';

/**
 * Developer Example Module Page
 *
 * Demonstrates the MVC flow and how data moves from Controller → Model → View.
 *
 * @package MVC
 * @category Developer
 */
 
 function dev_code($file)
{
    if (!file_exists($file)) {
        return "File not found.";
    }

    return htmlspecialchars(file_get_contents($file));
}

$flow = [
    "Router dispatched request to Developer Controller",
    "Controller executed example() method",
    "Controller would normally call a Model for data",
    "Model would query the database using the PDO wrapper",
    "Model returns data to Controller",
    "Controller passes data to the View",
    "View renders HTML output"
];

ob_start();
?>

<h1>Example Module</h1>

<p>
This page demonstrates how the MVC framework processes a request
and moves data through the system.
</p>

<hr>

<h2>Execution Trace</h2>

<ol>
<?php foreach ($flow as $step): ?>
    <li><?= htmlspecialchars($step) ?></li>
<?php endforeach; ?>
</ol>

<hr>

<h2>MVC Flow Diagram</h2>

<pre>
Request
 ↓
Router
 ↓
Controller
 ↓
Model
 ↓
Database
 ↓
Controller
 ↓
View
 ↓
HTML Output
</pre>

<p>
Controllers coordinate logic, models manage database interactions,
and views render the final output.
</p>

<hr>
<h2>Example Controller</h2>

<pre>
<?= dev_code(APPROOT . '/controllers/example.php'); ?>
</pre>
<hr>

<h2>Example Model</h2>

<pre>
<?= dev_code(APPROOT . '/models/example_model.php'); ?>
</pre>

<hr>

<h2>Example View</h2>

<pre>
<?= dev_code(APPROOT . '/views/public/example/index.php'); ?>
</pre>

<p>You can see this Page in action at <a href="/example">Example</a></p>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
require APPROOT . '/views/inc/foot.php';
