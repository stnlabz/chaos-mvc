<?php
require APPROOT . '/views/inc/head.php';
echo '<div class="container">';
/**
 * Developer Portal Index
 *
 * Provides the main entry point for MVC developer documentation.
 *
 * @package MVC
 * @category Developer
 */

ob_start();
?>

<h1>Developer Portal</h1>

<p>
Welcome to the MVC Developer Portal.  
This section explains how the system works and provides working examples
for contributors who want to build modules or extend the platform.
</p>

<hr>

<h2>Start Here</h2>

<p>
If you are new to the system, begin with the MVC execution flow.
It explains how requests move through the application.
</p>
<hr>

<h2>Architecture Overview</h2>

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
Controllers coordinate application logic, models manage database access,
and views render the final output presented to the user.
</p>

<hr>

<h2>Project Goals</h2>

<ul>
    <li>Maintain a clean and predictable MVC architecture</li>
    <li>Ensure all database operations use the approved wrapper</li>
    <li>Require PSR-compliant code and full DocBlock documentation</li>
    <li>Enforce AI and developer annotation protocols</li>
</ul>

<hr>

<h2>System Status</h2>

<ul>
    <li><b>MVC Version</b>: v1.1.5</li>
    <li><b>Router Status</b>: Locked</li>
    <li><b>Core Protection</b>: Active</li>
    <li><b>Annotation Policy</b>: Enforced</li>
</ul>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
echo '</div>';
require APPROOT . '/views/inc/foot.php';
