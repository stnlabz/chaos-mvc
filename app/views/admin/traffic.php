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
            <td><?= $log['created_at'] ?></td>
            <td><code><?= $log['ip'] ?></code></td>
            <td><span class="badge bg-secondary"><?= $log['method'] ?></span></td>
            <td><?= htmlspecialchars($log['uri']) ?></td>
            <td class="small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($log['user_agent']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
