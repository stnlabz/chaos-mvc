<?php
require APPROOT . '/views/inc/head.php';
/**
 * Markdown Renderer Documentation
 *
 * Explains how the render_md class converts Markdown
 * into HTML inside the MVC framework.
 *
 * @package MVC
 * @category Developer
 */

ob_start();
?>

<h1>Markdown Rendering</h1>

<p>
This MVC framework includes a Markdown renderer that converts Markdown text
into HTML before displaying it in the browser.
</p>

<hr>

<h2>How It Works</h2>

<pre>
Controller
 ↓
Markdown Renderer (render_md)
 ↓
HTML Output
</pre>

<p>
Controllers or views pass Markdown text to the renderer, which converts it to HTML.
</p>

<hr>

<h2>Example Usage</h2>

<pre>
$render = new render_md();

$text = "
# Hi

This is just a test `Markdown` message.

- You should **echo** this.
";

echo $render->markdown($text);
</pre>

<hr>

<h2>Example Markdown Input</h2>

<pre>
# Hi

This is just a test `Markdown` message.

- You should **echo** this.
</pre>

<hr>

<h2>Rendered Output</h2>

<h3>Hi</h3>

<p>This is just a test <code>Markdown</code> message.</p>

<ul>
<li>You should <strong>echo</strong> this.</li>
</ul>

<hr>

<h2>Where Markdown Is Used</h2>

<ul>
<li>Blog posts</li>
<li>Developer documentation</li>
<li>Dynamic content modules</li>
</ul>

<p>
Using Markdown allows developers and writers to create structured content
without writing HTML directly.
</p>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
require APPROOT . '/views/inc/foot.php';
