<?php
require APPROOT . '/views/inc/head.php';
/**
 * View Documentation
 *
 * Explains how the view pulls data from the controller
 * and presents itself.
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

ob_start();
?>

<h1>The View</h1>

<p>
The final piece to the MVC Flow is the view, where your data gets presented. But its not just presenting the data, its the visual aestetic of your site.
The Files included are:
<pre>
app/views/inc/head.php
app/views/inc/nav.php
app/views/inc/foot.php
public/assets/css/site.css
app/views/public/home/index.php
</pre>
</p>

<hr>

<h2>How It Works</h2>

<pre>
Controller
 ↓
View
 ↓
HTML Output
</pre>

<p>
Controllers pass data or call upon the view.
<pre>
$this->view('public/example/index', $data);
</pre>
</p>

<hr>

<h2>Example Head</h2>
<code>app/views/inc/head.php</code>
<pre>
<?= dev_code(APPROOT . '/views/inc/example_head.php'); ?>
</pre>

<hr>

<h2>Example Nav</h2>
<code>app/views/inc/nav.php</code>
<pre>
<?= dev_code(APPROOT . '/views/inc/example_nav.php'); ?>
</pre>

<hr>

<h2>Example Foot</h2>
<code>app/views/inc/foot.php</code>
<pre>
<?= dev_code(APPROOT . '/views/inc/example_foot.php'); ?>
</pre>
<hr>

<h2>Example CSS</h2>
<code>public/assets/css/site.css</code>
<pre>
<?= dev_code(PUBROOT . '/assets/css/example_site.css'); ?>
</pre>
<hr>

<h2>Home</h2>
<code>app/views/public/home/index.php</code>
<p>This view is what people see when they arrive at your URL.</p>
<pre>
<?= dev_code(APPROOT . '/views/public/home/example_index.php'); ?>
</pre>
<hr>

<h2>Java Script</h2>
<code>public/assets/js/site.js</code>
<p>A front-end developer should be well rounded, including some knowledge in <code>JavaScript</code>.</p>
<pre>
<?= dev_code(PUBROOT . '/assets/js/site.js'); ?>
</pre>
</p>
<p>
Learning to theme your site is one of the most important aspects of development cause without it, your site will not last more than 5 seconds for the average person cruising the web.
</p>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
require APPROOT . '/views/inc/foot.php';
