<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><a href="/admin">Admin</a> >> <strong>Traffic</strong></small></p>
<div class="container">
 
 <table class="table table-sm table-hover">
    <thead class="table-dark">
        <tr>
            <th>Time (UTC)</th>
            <th>IP</th>
            <th>Method</th>
            <th>URI</th>
            <th>Agent</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['logs'] as $log): ?>
        <tr>
            <td><?= htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><code><?= htmlspecialchars((string) $log['ip'], ENT_QUOTES, 'UTF-8') ?></code></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $log['method'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td><?= htmlspecialchars((string) $log['uri'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="small text-truncate" style="max-width: 200px;"><?= htmlspecialchars((string) ($log['user_agent'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
