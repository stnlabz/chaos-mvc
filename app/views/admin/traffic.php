<?php /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */ ?>
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
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td><code><?= htmlspecialchars($log['ip']) ?></code></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($log['method']) ?></span></td>
            <td><?= htmlspecialchars($log['uri']) ?></td>
            <td class="small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($log['user_agent']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
<?php /* [End AI:GPT-5.6 Sol] */ ?>
