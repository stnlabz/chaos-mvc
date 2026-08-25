<?php /* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */ ?>
<?php require_once APPROOT . '/views/inc/head.php'; ?>
<div class="account-wrapper" style="background: #f4f4f4; min-height: 80vh; padding: 40px 20px;">
    <div class="account-container" style="max-width: 450px; margin: 0 auto; padding: 40px; border: 1px solid #e0e0e0; background: #fff;">
        <h2 style="margin-top: 0;">Set New Password</h2>
        <?php if (!empty($data['error'])): ?>
            <p style="color: #d32f2f;"><?= htmlspecialchars($data['error']) ?></p>
        <?php endif; ?>
        <form action="/reset-password/<?= htmlspecialchars($data['token']) ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($this->csrf_token()) ?>">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px;">NEW PASSWORD</label>
                <input type="password" name="password" required autofocus style="width: 100%; padding: 10px; border: 1px solid #ccc;">
            </div>
            <button type="submit" style="width: 100%; background: #333; color: white; padding: 12px; border: none; cursor: pointer; font-weight: 600;">UPDATE CREDENTIALS</button>
        </form>
    </div>
</div>
<?php require_once APPROOT . '/views/inc/foot.php'; ?>
<?php /* [End AI:GPT-5.6 Sol] */ ?>
