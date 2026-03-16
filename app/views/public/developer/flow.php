<?php
require APPROOT . '/views/inc/head.php';
ob_start();
?>
<div class="container">
<h1>MVC Execution Flow</h1>

<p>
This page explains how a request moves through the MVC framework from the
moment a user visits a URL until the final HTML is rendered.
</p>

<hr>

<h2>Execution Path</h2>

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

<hr>

<h2>Step-by-Step Explanation</h2>

<h3>1. Request</h3>
<p>
A user visits a URL such as:
</p>

<pre>/posts/my-article</pre>

<p>
The web server forwards the request to the MVC application.
</p>

<h3>2. Router</h3>
<p>
The router determines which controller should handle the request.
It parses the URL and maps it to a controller and method.
</p>

<h3>3. Controller</h3>
<p>
The controller receives the request and decides what logic should run.
Controllers coordinate communication between models and views.
</p>

<h3>4. Model</h3>
<p>
If data is required, the controller calls a model.  
Models are responsible for interacting with the database.
</p>

<h3>5. Database</h3>
<p>
The model executes queries using the approved database wrapper and retrieves data.
</p>

<h3>6. Controller (again)</h3>
<p>
The model returns the result to the controller.  
The controller prepares the data for the view.
</p>

<h3>7. View</h3>
<p>
The controller sends the data to the view, which renders HTML.
Views should contain minimal logic and focus on presentation.
</p>

<h3>8. HTML Output</h3>
<p>
The rendered page is returned to the browser and displayed to the user.
</p>

<hr>

<h2>Example Flow</h2>

<pre>
User visits: /posts/my-article

Router → posts controller
Controller → posts_model
Model → database query
Model → returns article data
Controller → passes data to view
View → renders HTML page
</pre>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
echo '</div>';
require APPROOT . '/views/inc/foot.php';
