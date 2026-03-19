<?php require APPROOT . '/views/inc/head.php'; ?>
<div class="container">
  <div class="row">
    <h1>Issues</h1>
    <p>Below is a collection of issues listed by latest date, closed and open will always be visible as we strive to maintain transparency.</p>
    <p>You <b>MUST</b> have a registered <b><a href="/signup">Account</a></b> in order to submit any issues.</p>
  </div>
  <div class="row">
<section class="bug-list-container">
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/bugs/create" style="padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 3px; font-weight: bold;">+ Report issue</a>
        <?php endif; ?>
    </header>

    <div class="bug-table">
        <?php if (!empty($list)): ?>
            <?php foreach ($list as $b): ?>
                <div class="bug-row" style="margin-bottom: 20px; padding: 15px; border-bottom: 1px solid #eee; background: #fafafa;">
                    <div class="bug-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>#<?= substr($b['bug_hash'] ?? '', 0, 6); ?></strong>
                            <a href="/bugs/show/<?= substr($b['bug_hash'] ?? '', 0, 6); ?>" style="margin-left: 10px; font-weight: bold; text-decoration: none; color: #000;">
                                <?= htmlspecialchars($b['title'] ?? 'no title'); ?>
                            </a>
                        </div>
                        <span class="status-badge" style="padding: 4px 8px; border-radius: 3px; font-size: 0.8em; font-weight: bold; background: <?= ($b['status'] === 'open') ? '#dfd' : '#eee'; ?>; color: <?= ($b['status'] === 'open') ? '#060' : '#666'; ?>;">
                            <?= strtoupper($b['status'] ?? 'open'); ?>
                        </span>
                    </div>
                    
                    <div class="bug-preview" style="font-size: 0.9em; color: #444; margin: 10px 0;">
                        <?php 
                            $desc = $b['description'] ?? '';
                            echo htmlspecialchars(strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc); 
                        ?>
                    </div>
                    
                    <div class="bug-meta" style="font-size: 0.8em; color: #888;">
                        Reported on: <?= $b['created_at'] ?? ''; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">
                <p><strong>No issues found.</strong> System operating within expected parameters.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
  </div>
</div>
<?php require APPROOT . '/views/inc/foot.php'; ?>
