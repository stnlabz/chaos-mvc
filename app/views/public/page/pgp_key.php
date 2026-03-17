<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><a href="/">Home</a> >> <strong>PGP Key</strong></small></p>
<div class="container mt-5">
    <div class="card shadow-sm border-0">
        <div class="card-body p-5">

            <h1 class="display-6 mb-4">
                <?= htmlspecialchars($data['title']); ?>
            </h1>
            
            <p class="text-secondary" style="font-size: 0.9em;">
            Use the following pgp public key to encrypt all vulnerability reports and sensitive correspondence.
            </p>
            <hr>
            <p><b>release@chaos-mvc.org</b></p>
            <pre class="p-4 bg-light border rounded small">
		<?= htmlspecialchars($data['content']); ?>
            </pre>
        </div>
    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>

