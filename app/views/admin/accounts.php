<?php require APPROOT . '/views/inc/head.php'; ?>
<p><small><a href="/admin">Admin</a> >> <strong>Accounts</strong></small></p>
<div class="container mt-4">

<h2>Account Management</h2>
<?php if(isset($_SESSION['msg'])): ?>
    <div class="alert alert-<?= $_SESSION['msg_type']; ?> mt-3">
        <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
    </div>
<?php endif; ?>

<table class="table table-bordered">
<thead>
<tr>
<th>ID</th>
<th>Username</th>
<th>Name</th>
<th>Level</th>
<th>Password</th>
<th>Delete</th>
</tr>
</thead>

<tbody>

<?php foreach ($data['accounts'] as $a): ?>

<tr>

<td><?php echo $a['id']; ?></td>

<td><?php echo htmlspecialchars($a['username']); ?></td>

<td><?php echo htmlspecialchars($a['display_name']); ?></td>

<td><?php echo $a['user_level']; ?></td>

<td>

<form method="POST" action="<?php echo URLROOT; ?>/admin_accounts/password/<?php echo $a['id']; ?>">
<input type="password" name="password" placeholder="new password" required>
<button type="submit">change</button>
</form>

</td>

<td>

<?php if ($a['id'] != ($_SESSION['user_id'] ?? 0)): ?>
    <form method="POST" action="<?php echo URLROOT; ?>/auth/delete/<?php echo $a['id']; ?>">
        <button type="submit">delete</button>
    </form>
<?php else: ?>
    <strong>(You)</strong>
<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<hr>

<h3>Create Account</h3>

<form method="POST" action="<?php echo URLROOT; ?>/admin/accounts/create">

<div class="mb-2">
<label>Username</label>
<input type="text" name="username" class="form-control" required>
</div>

<div class="mb-2">
<label>Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<div class="mb-2">
<label>Name</label>
<input type="text" name="display_name" class="form-control">
</div>

<div class="mb-3">
<label>User Level</label>
<input type="number" name="user_level" class="form-control" value="1">
</div>

<button type="submit" class="btn btn-primary">Create Account</button>

</form>

</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
