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
<p>
All certification standards, examples, and required structure are defined within the Developers Portal.<br>
If your work does not align with those standards, it will not be accepted.<br>
Refer to the <a href="/developer">Developers Portal</a> before beginning any certification work.
</p>
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
<p>These standards are non-negotiable and enforced across the Chaos MVC ecosystem.</p>
<p>Every module is cryptographically bound to a developer's GPG Key.</p>

<p>Refer to the <strong><a href="/developer">Developers Portal</a></strong> to view the <b>Example Module</b>. Submissions that do not meet these standards will be rejected. Candidates are expected to revise and resubmit in alignment with the framework's structure.</p>
<p align="center">The Symbol of Choas Excellence<br><img alt="Chaos Certified Program" src="/assets/img/chaos_certified.png" height="300" width="300"></p>

<h3>Marketplace Access</h3>
<p>The Chaos MVC marketplace will only accept themes, modules, and extensions developed by certified Chaos developers.</p>
<p>Certification is required to distribute within the ecosystem.</p>

<h3>Why This Exists</h3>
<p>This standard ensures that all distributed components follow the architecture, maintain quality, and remain compatible across the system.</p>
<p>No un certified code will be accepted.</p>

<?php require APPROOT . '/views/inc/foot.php'; ?>
