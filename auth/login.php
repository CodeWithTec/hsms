<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) redirect_dashboard();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = post('username');
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is ' . $user['status'] . '. Contact the admin.';
        } else {
            // Build session
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'role'     => $user['role'],
            ];

            // Update last_login
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            log_activity('login', 'User logged in');

            redirect_dashboard();
        }
    }
}

$settings = get_settings();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($settings['school_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { min-height: 100vh; display:flex; align-items:center; justify-content:center;
           background: linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%); padding:20px; }
    .login-card { max-width:420px; width:100%; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,.2); }
</style>
</head>
<body>
<div class="card login-card">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <i class="bi bi-mortarboard-fill text-primary" style="font-size:2.5rem;"></i>
            <h4 class="mt-2 mb-1"><?= e($settings['school_name']) ?></h4>
            <p class="text-muted small mb-0">High School Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>
        <?= show_flash() ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label small">Username or Email</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100 py-2">Log In</button>
        </form>

        <p class="text-center text-muted small mt-4 mb-0">
            &copy; <?= date('Y') ?> <?= e($settings['school_name']) ?>
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>