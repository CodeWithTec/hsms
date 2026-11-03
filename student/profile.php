<?php
$pageTitle = 'My Profile';
$section   = 'Student · Profile';
require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$student = current_student();
if (!$student) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No student profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$tab = get('tab','info');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'update_info') {
        $phone = post('phone'); $addr = post('address'); $county = post('county');
        $gn = post('guardian_name'); $gp = post('guardian_phone');
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = upload_file('photo','students', ALLOWED_IMAGE_TYPES);
            if ($photo === null) flash_redirect('error','Upload failed.','student/profile.php');
        }
        if ($photo) {
            $pdo->prepare("UPDATE students SET phone=?,address=?,county=?,guardian_name=?,guardian_phone=?,photo=? WHERE id=?")
                ->execute([$phone,$addr,$county,$gn,$gp,$photo,$student['id']]);
        } else {
            $pdo->prepare("UPDATE students SET phone=?,address=?,county=?,guardian_name=?,guardian_phone=? WHERE id=?")
                ->execute([$phone,$addr,$county,$gn,$gp,$student['id']]);
        }
        flash_redirect('success','Profile updated.','student/profile.php');
    }
    if ($fa === 'change_password') {
        $cur = post('current'); $new = post('new'); $con = post('confirm');
        $u = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
        $u->execute([user_id()]);
        if (!password_verify($cur, $u->fetchColumn())) flash_redirect('error','Current password wrong.','student/profile.php?tab=password');
        if (strlen($new) < 6) flash_redirect('error','Password too short.','student/profile.php?tab=password');
        if ($new !== $con) flash_redirect('error','Passwords do not match.','student/profile.php?tab=password');
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),user_id()]);
        flash_redirect('success','Password changed.','student/profile.php?tab=password');
    }
}
$student = current_student();
$classQ = $pdo->prepare("SELECT name FROM classes WHERE id=?");
$classQ->execute([$student['class_id'] ?? 0]);
$className = $classQ->fetchColumn();
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-3">My Profile</h4>
    <?= show_flash() ?>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab==='info'?'active':'' ?>" href="?tab=info">Profile</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='password'?'active':'' ?>" href="?tab=password">Password</a></li>
    </ul>

    <?php if ($tab === 'info'): ?>
        <div class="card"><div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3 text-center">
                    <?php if ($student['photo']): ?>
                        <img src="<?= UPLOAD_URL.e($student['photo']) ?>" class="rounded-circle" style="width:120px;height:120px;object-fit:cover">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                             style="width:120px;height:120px;font-size:2.5rem">
                            <?= strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-9">
                    <h5><?= e($student['first_name'].' '.$student['last_name']) ?></h5>
                    <p class="mb-1"><strong>Student ID:</strong> <code><?= e($student['student_id']) ?></code></p>
                    <p class="mb-1"><strong>Class:</strong> <?= e($className ?? '—') ?></p>
                    <p class="mb-1"><strong>Gender:</strong> <?= e(ucfirst($student['gender'] ?? '—')) ?></p>
                    <p class="mb-1"><strong>DOB:</strong> <?= e($student['dob'] ?? '—') ?></p>
                </div>
            </div>
            <hr>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="update_info">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($student['phone']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">County</label>
                        <select name="county" class="form-select">
                            <option value="">—</option>
                            <?php foreach (LIBERIA_COUNTIES as $c): ?>
                                <option value="<?= e($c) ?>" <?= ($student['county']??'')===$c?'selected':'' ?>><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-12"><label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= e($student['address']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Guardian Name</label>
                        <input type="text" name="guardian_name" class="form-control" value="<?= e($student['guardian_name']??'') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Guardian Phone</label>
                        <input type="text" name="guardian_phone" class="form-control" value="<?= e($student['guardian_phone']??'') ?>"></div>
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
                    <div class="col-12"><label class="form-label">Confirm</label>
                        <input type="password" name="confirm" class="form-control" required minlength="6"></div>
                    <div class="col-12"><button class="btn btn-primary px-4">Change Password</button></div>
                </div>
            </form>
        </div></div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>