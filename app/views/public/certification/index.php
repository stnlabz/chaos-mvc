<?php require APPROOT . '/views/inc/head.php'; ?>

<h1>Chaos Certified</h1>
<p>This is the standard for the Chaos MVC ecosystem. Competency is our watchword.</p>
<p>
<img src="https://img.shields.io/badge/Chaos-Modules-red" alt="Chaos - Modules">
<img src="https://img.shields.io/badge/Chaos-Themes-red" alt="Chaos - Themes">
<img src="https://img.shields.io/badge/Chaos-Certified-red" alt="Chaos - Certified">
</p>

<hr>

<h2>The Certification Process</h2>
<p>To become certified, you must follow the steps provided below.</p>
<p>It is recommended that each potential candidate, install the <b>Chaos MVC</b> and build on their domain as their sandbox as we will <b>NOT</b> provide a sandbox environment for anyone.</p>
<h3>Levels of Certification</h3>
<p>
1. <b><a href="/developer/theme">Theme</a> Developer</b>: Is geared toward this <em>front-end</em> Developers that love to work with <code>CSS</code>, <code>HTML</code>, & <code>JS</code>.<br>
2. <b><a href="/developer/example">Module</a> Developer</b>: Is geared toward the <em>back-end</em> Developers, that prefer to make things work from that perspective, with <code>PHP</code> & <code>MySQL</code>.<br>
3. <b>Chaos Certified Developer</b>: When you have completed 1 & 2, and shown that you have the discipline to follow strcutured guidance in reference to development, you may be awarded the <b>Chaos Certified Developer</b> badge.
</p>

<ul>
    <?php foreach($data['requirements'] as $req): ?>
        <li><?= htmlspecialchars($req); ?></li>
    <?php endforeach; ?>
</ul>

<hr>

<h3>The Requirement</h3>
<em>That's written in stone.</em>
<p>Every module is cryptographically bound to a developer's GPG Key and a static IP address. If any deviation gets detected, the key and IP are permanently revoked by Sentinel.</p>

<p>Refer to the <strong><a href="/developer">Developers Portal</b></strong> to view the <b>Example Module</b>. If your code does not mirror that DNA, it will be rejected without appeal.</p>
<p align="center">The Symbol of Choas Excellence<br><img alt="Chaos Certified Program" src="/assets/img/chaos_certified.png" height="300" width="300"></p>

<?php require APPROOT . '/views/inc/foot.php'; ?>
