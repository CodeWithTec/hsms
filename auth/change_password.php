<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$forced = (bool)($force ?? false);

// Check if user really needs to change
global $pdo;
$u = $pdo->prepare("SELECT must_change_password FROM users WHERE id=?");
$u->execute([user_id()]);
$needsChange = (bool)$u->fetchColumn();

if (!$needsChange && empty($_GET['optional'])) {
    redirect_dashboard();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $new = post('new');
    $con = post('confirm');
    if (strlen($new) < 8) {
        flash('error','Password must be at least 8 characters.');
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        flash('error','Password must include uppercase, lowercase, and a number.');
    } elseif ($new !== $con) {
        flash('error','Passwords do not match.');
    } else {
        $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
        log_activity('change_password','Password set on first login');
        flash('success','Password updated. Welcome!');
        redirect_dashboard();
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Change Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f0f2f5;padding:20px;}
.card{max-width:480px;width:100%;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.1);}</style>
</head><body>
<div class="card"><div class="card-body p-4">
    <div class="text-center mb-3">
        <i class="bi bi-shield-lock-fill text-primary" style="font-size:2.5rem;"></i>
        <h4 class="mt-2 mb-1">Set Your Password</h4>
        <p class="text-muted small mb-0">For your security, please set a new password before continuing.</p>
    </div>
    <?= show_flash() ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new" class="form-control" required minlength="8" autofocus>
            <small class="text-muted">At least 8 chars, with uppercase, lowercase, and a number.</small>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm" class="form-control" required minlength="8">
        </div>
        <button class="btn btn-primary w-100">Save Password</button>
    </form>
</div></div>
</body></html>