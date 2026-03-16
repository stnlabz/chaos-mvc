<?php require APPROOT . '/views/inc/head.php'; ?>
<div class="container" style="max-width: 400px; margin-top: 50px;">
    <h2>Register Account</h2>
    <?php if(isset($data['error'])): ?>
        <div class="alert alert-danger"><?= $data['error'] ?></div>
    <?php endif; ?>
    <form action="/auth/signup" method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email_address" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Display Name</label>
            <input type="text" name="display_name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign Up</button>
    </form>
</div>
<?php require_once APPROOT . '/views/inc/foot.php'; ?>
