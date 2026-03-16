<?php
require APPROOT . '/views/inc/head.php';
/**
 * Development Rules
 *
 * Defines contribution requirements and development standards
 * for this MVC project.
 *
 * @package MVC
 * @category Developer
 */

ob_start();
?>

<h1>Development Rules</h1>

<p>
All contributors must follow these rules when working within this MVC project.
These standards exist to maintain system integrity, traceability,
and architectural consistency.
</p>

<hr>

<h2>GitHub Requirement</h2>

<p>
All architectural changes must be committed through GitHub.
Core modifications require approval before merge.
</p>

<p>
Direct edits to production systems without repository tracking
are not permitted.
</p>

<hr>

<h2>Mandatory AI Annotation Policy</h2>

<p>
Any code written or modified by an AI must be annotated.
This ensures traceability and accountability for all automated changes.
</p>

<pre>
/* [AI:MODEL_NAME | YYYY-MM-DD HH:MM:SS UTC] */
modified code
/* [End AI:MODEL_NAME] */
</pre>

<p>
Human approval annotations must accompany AI modifications
when applied to architectural components.
</p>

<hr>

<h2>Lowercase Naming Policy</h2>

<p>
This MVC enforces strict lowercase naming conventions.
</p>

<ul>
<li>Controller files must be lowercase</li>
<li>Model files must be lowercase</li>
<li>View directories must be lowercase</li>
<li>Class names must be lowercase</li>
</ul>

<p>
Uppercase file or class naming is not permitted.
This rule ensures predictable behavior across filesystems
and maintains consistency with the project's architectural design.
</p>

<hr>

<h2>PSR Compliance</h2>

<p>
All code must follow PSR formatting standards,
specifically PSR-12 for structure and readability.
</p>

<hr>

<h2>PSR-12 Example</h2>

<p>
The following example demonstrates expected PSR-12 formatting:
</p>

<pre>
&lt;?php

/**
 * Example Controller
 *
 * Demonstrates PSR-12 compliant structure.
 */
class example extends controller
{
    /**
     * Default index method.
     *
     * @return void
     */
    public function index()
    {
        $data = [
            'title' => 'Example Module',
        ];

        $this->view('public/example/index', $data);
    }
}
</pre>

<hr>

<h2>DocBlock Requirement</h2>

<p>
All classes and public methods must include DocBlock documentation.
</p>

<p>
DocBlocks must describe the purpose of the code and expected behavior.
</p>

<pre>
/**
 * Retrieves records from the database.
 *
 * @param string $slug
 * @return array|null
 */
</pre>

<p>
Incomplete or missing documentation is considered a code quality violation.
</p>

<hr>

<h2>Core Protection</h2>

<p>
Files located in the following directory are protected:
</p>

<pre>/app/core</pre>

<p>
Changes to core components require explicit approval.
Unauthorized modifications to core infrastructure are prohibited.
</p>

<hr>

<h2>Three-Strike Enforcement Policy</h2>

<ul>
<li>Violation 1 — Warning</li>
<li>Violation 2 — Final Warning</li>
<li>Violation 3 — Removal from project</li>
</ul>

<p>
These rules exist to maintain a stable and predictable development environment.
</p>

<hr>

<h2>Project Philosophy</h2>

<p>
This MVC framework prioritizes:
</p>

<ul>
<li>Clean architecture</li>
<li>Strict documentation</li>
<li>Accountable development</li>
<li>Minimal complexity</li>
</ul>

<p>
Consistency and discipline ensure the system remains maintainable
as the project evolves.
</p>

<?php
$content = ob_get_clean();
require APPROOT . '/views/public/developer/layout.php';
require APPROOT . '/views/inc/foot.php';
