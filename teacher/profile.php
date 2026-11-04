<?php
$pageTitle = 'My Profile';
$section   = 'Teacher · Profile';
require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_teacher.php';

global $pdo;
$teacher = current_teacher();
if (!$teacher) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No teacher profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }

$tab = get('tab','info');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'update_info') {
        $phone = post('phone');
        $addr = post('address');
        $county = post('county');
        $qual = post('qualification');
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = upload_file('photo','teachers', ALLOWED_IMAGE_TYPES);
            if ($photo === null) { flash('error','Photo upload failed.'); flash_redirect('error','Upload failed.','teacher/profile.php'); }
        }
        if ($photo) {
            $pdo->prepare("UPDATE teachers SET phone=?, address=?, county=?, qualification=?, photo=? WHERE id=?")
                ->execute([$phone,$addr,$county,$qual,$photo,$teacher['id']]);
        } else {
            $pdo->prepare("UPDATE teachers SET phone=?, address=?, county=?, qualification=? WHERE id=?")
                ->execute([$phone,$addr,$county,$qual,$teacher['id']]);
        }
        log_activity('update_profile','Teacher updated own profile');
        flash_redirect('success','Profile updated.','teacher/profile.php');
    }
    if ($fa === 'change_password') {
        $cur = post('current');
        $new = post('new');
        $con = post('confirm');
        $u = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $u->execute([user_id()]);
        $hash = $u->fetchColumn();
        if (!password_verify($cur, $hash)) {
            flash_redirect('error','Current password is incorrect.','teacher/profile.php?tab=password');
        }
        if (strlen($new) < 6) {
            flash_redirect('error','New password must be at least 6 characters.','teacher/profile.php?tab=password');
        }
        if ($new !== $con) {
            flash_redirect('error','New passwords do not match.','teacher/profile.php?tab=password');
        }
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), user_id()]);
        log_activity('change_password','Teacher changed own password');
        flash_redirect('success','Password changed.','teacher/profile.php?tab=password');
    }
}

$teacher = current_teacher(); // refresh
?>
<div class="p-4">
    <h4 class="mb-3">My Profile</h4>
    <?= show_flash() ?>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab==='info'?'active':'' ?>" href="?tab=info">Profile Information</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='password'?'active':'' ?>" href="?tab=password">Change Password</a></li>
    </ul>

    <?php if ($tab === 'info'): ?>
        <div class="card"><div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3 text-center">
                    <?php if ($teacher['photo']): ?>
                        <img src="<?= UPLOAD_URL.e($teacher['photo']) ?>" class="rounded-circle mb-2" style="width:120px;height:120px;object-fit:cover">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:120px;height:120px;font-size:2.5rem">
                            <?= strtoupper(substr($teacher['first_name'],0,1).substr($teacher['last_name'],0,1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h5><?= e($teacher['first_name'].' '.$teacher['last_name']) ?></h5>
                    <p class="mb-1"><strong>Staff ID:</strong> <code><?= e($teacher['staff_id']) ?></code></p>
                    <p class="mb-1"><strong>Gender:</strong> <?= e(ucfirst($teacher['gender']??'—')) ?></p>
                    <p class="mb-1"><strong>DOB:</strong> <?= e($teacher['dob']??'—') ?></p>
                </div>
            </div>
            <hr>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="update_info">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($teacher['phone']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">County</label>
                        <select name="county" class="form-select">
                            <option value="">—</option>
                            <?php foreach (LIBERIA_COUNTIES as $c): ?>
                                <option value="<?= e($c) ?>" <?= ($teacher['county']??'')===$c?'selected':'' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= e($teacher['address']??'') ?>"></div>
                    <div class="col-12"><label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="<?= e($teacher['qualification']??'') ?>"></div>
                    <div class="col-12"><label class="form-label">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*"></div>
                    <div class="col-12"><button class="btn btn-primary px-4">Save Changes</button></div>
                </div>
            </form>
        </div></div>
    <?php else: ?>
        <div class="card"><div class="card-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="change_password">
                <div class="row g-3" style="max-width:500px">
                    <div class="col-12"><label class="form-label">Current Password</label>
                        <input type="password" name="current" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">New Password</label>
                        <input type="password" name="new" class="form-control" required minlength="6"></div>
                    <div class="col-12"><label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm" class="form-control" required minlength="6"></div>
                    <div class="col-12"><button class="btn btn-primary px-4">Change Password</button></div>
                </div>
            </form>
        </div></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>