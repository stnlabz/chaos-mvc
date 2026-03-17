<?php require APPROOT . '/views/inc/head.php'; ?>

<h1>Chaos Certified</h1>
<p>This is the standard for the Chaos MVC ecosystem. Competency is our watchword.</p>
<p><img src="https://img.shields.io/badge/Chaos-Certified-red" alt="Chaos - Certified"></p>

<hr>

<h2>The Certification Process</h2>
<p>To become certified, you must create a module that matches the <b>Example Module</b> format and follow the <b>MVC Flow</b> of this platform.</p>
<p>It is recommended that each potential candidate, install the <b>Chaos MVC</b> and build on their domain as their sandbox as we will <b>NOT</b> provide a sandbox environment for anyone.</p>

<ul>
    <?php foreach($data['requirements'] as $req): ?>
        <li><?= htmlspecialchars($req); ?></li>
    <?php endforeach; ?>
</ul>

<hr>

<h3>The "Soldier's Seal"</h3>
<p>Every module is cryptographically bound to a developer's GPG Key and a static IP address. If any deviation gets detected, the key and IP are permanently revoked by Sentinel.</p>

<p>Refer to the <strong><a href="/developer">Developers Portal</b></strong> to view the <b>Example Module</b>. If your code does not mirror that DNA, it will be rejected without appeal.</p>
<p align="center">The Symbol of Choas Excellence<br><img alt="Chaos Certified Program" src="/assets/img/chaos_certified.png" height="300" width="300"></p>

<?php require APPROOT . '/views/inc/foot.php'; ?>
