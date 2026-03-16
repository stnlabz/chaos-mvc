<?php
require APPROOT . '/views/inc/head.php';
/**
 * Database Wrapper Documentation
 *
 * Explains how the MVC database wrapper is used by models
 * to safely interact with the database.
 *
 * @package MVC
 * @category Developer
 */

ob_start();
?>

<h1>Database Wrapper</h1>

<p>
This MVC framework uses a database wrapper to standardize how queries are executed.
Developers should never run raw database operations inside controllers or views.
All database interaction must occur inside models.
</p>

<hr>

<h2>MVC Database Flow</h2>

<pre>
Controller
 ↓
Model
 ↓
Database Wrapper
 ↓
Database
</pre>

<p>
Controllers request data from models.  
Models execute database queries using the wrapper functions.
</p>

<hr>

<h2>Common Wrapper Methods</h2>

<h3>query()</h3>

<p>
Executes a database query using the wrapper.
</p>

<pre>
$this->query("SELECT * FROM posts");
</pre>

<p>
This prepares and executes a query using the internal database connection.
</p>

<hr>

<h3>fetch()</h3>

<p>
Retrieves a single record from the result set.
</p>

<pre>
$post = $this->fetch(
    "SELECT * FROM posts WHERE slug = ? LIMIT 1",
    [$slug]
);
</pre>

<p>
Use this when you expect a single row.
</p>

<hr>

<h3>fetchAll()</h3>

<p>
Retrieves multiple rows from a query.
</p>

<pre>
$posts = $this->query(
    "SELECT * FROM posts ORDER BY created_at DESC"
)->fetchAll();
</pre>

<p>
Use this when retrieving multiple records.
</p>

<hr>

<h2>Example Model</h2>

<pre>
class posts_model extends model
{
    public function get_all()
    {
        return $this->query(
            "SELECT * FROM posts ORDER BY created_at DESC"
        )->fetchAll();
    }

    public function get_by_slug($slug)
    {
        return $this->fetch(
            "SELECT * FROM posts WHERE slug = ? LIMIT 1",
            [$slug]
        );
    }
}
</pre>

<hr>

<h2>Important Rules</h2>

<ul>
<li>Database queries belong inside models only.</li>
<li>Controllers coordinate logic but should not query the database directly.</li>
<li>Views must never access the database.</li>
<li>Always use the approved database wrapper methods.</li>
</ul>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
require APPROOT . '/views/inc/foot.php';
