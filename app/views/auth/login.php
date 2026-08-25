<?php require_once APPROOT . '/views/inc/head.php'; ?>
<div class="login-container" style="max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
    <h2 style="margin-top: 0; border-bottom: 2px solid #333; padding-bottom: 10px;">Website Access</h2>
    
    <?php if (isset($error)): ?>
        <p style="color: #d32f2f; background: #fdecea; padding: 10px; border-radius: 4px;"><?= $error ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['signup']) && $_GET['signup'] == 'success'): ?>
        <p style="color: #2e7d32; background: #edf7ed; padding: 10px; border-radius: 4px;">Registration successful! Please log in.</p>
    <?php endif; ?>

    <?php if (isset($_GET['reset']) && $_GET['reset'] == 'success'): ?>
        <p style="color: #2e7d32; background: #edf7ed; padding: 10px; border-radius: 4px;">Password updated. You can now log in.</p>
    <?php endif; ?>

    <form action="/auth/login" method="POST">
        <div style="margin-bottom: 15px;">
            <label for="username" style="font-weight: bold;">Username:</label><br>
            <input type="text" name="username" id="username" required style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between;">
                <label for="password" style="font-weight: bold;">Password:</label>
                <a href="/forgot-password" style="font-size: 0.85em; color: #555; text-decoration: none;">Forgot password?</a>
            </div>
            <input type="password" name="password" id="password" required style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <button type="submit" class="btn" style="width: 100%; background: #333; color: white; padding: 10px; border: none; border-radius: 4px; cursor: pointer;">Login</button>
    </form>
    
    <div style="margin-top: 20px; text-align: center; font-size: 0.9em;">
        New here? <a href="/signup" style="color: #333; font-weight: bold;">Create an account</a>
    </div>
</div>
<?php require_once APPROOT . '/views/inc/foot.php'; ?>
