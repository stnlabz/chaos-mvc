<?php require_once APPROOT . '/views/inc/head.php'; ?>
<div class="login-container" style="max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
    <h2>Recover Account</h2>
    <p style="color: #666; font-size: 0.9em;">Enter your email address and we'll send you a link to reset your password.</p>

    <?php if (isset($success)): ?>
        <p style="color: #2e7d32; background: #edf7ed; padding: 10px; border-radius: 4px;"><?= $success ?></p>
        <p><a href="/login">Return to Login</a></p>
    <?php else: ?>
        <form action="/auth/forgot_password" method="POST">
            <div style="margin-bottom: 15px;">
                <label for="email" style="font-weight: bold;">Email Address:</label><br>
                <input type="email" name="email" id="email" required style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <button type="submit" class="btn" style="width: 100%; background: #333; color: white; padding: 10px; border: none; border-radius: 4px;">Send Reset Link</button>
        </form>
    <?php endif; ?>
</div>
<?php require_once APPROOT . '/views/inc/foot.php'; ?>
