<?php
$current = $_SERVER['REQUEST_URI'] ?? '';
?>
<div class="developer-docs">
  <aside class="developer-nav">

<h3>Developer Docs</h3>

<ul>
<li class="<?= ($current == '/developer') ? 'active' : '' ?>">
<a href="/developer">Start Here</a>
</li>

<li class="<?= (strpos($current, '/developer/flow') !== false) ? 'active' : '' ?>">
<a href="/developer/flow">MVC Flow</a>
</li>

<li class="<?= (strpos($current, '/developer/example') !== false) ? 'active' : '' ?>">
<a href="/developer/example">Example Module</a>
</li>

<li class="<?= (strpos($current, '/developer/database') !== false) ? 'active' : '' ?>">
<a href="/developer/database">Database Wrapper</a>
</li>

<li class="<?= (strpos($current, '/developer/markdown') !== false) ? 'active' : '' ?>">
<a href="/developer/markdown">Markdown Renderer</a>
</li>

<li class="<?= (strpos($current, '/developer/rules') !== false) ? 'active' : '' ?>">
<a href="/developer/rules">Development Rules</a>
</li>

</ul>

</aside>

    <main class="developer-content">
        <?= $content ?>
    </main>

</div>
