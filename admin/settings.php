<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
// require_once __DIR__ . '/../includes/header.php';
// require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = [
        'school_name','school_address','school_phone','school_email',
        'current_session','current_semester','current_marking',
        'currency','currency_symbol','passing_grade'
    ];
    $vals = [];
    foreach ($fields as $f) $vals[$f] = post($f);

    $logo = null;
    if (!empty($_FILES['school_logo']['name'])) {
        $logo = upload_file('school_logo','school', ALLOWED_IMAGE_TYPES);
        if ($logo === null) {
            flash('error','Logo upload failed (JPG/PNG, max 2MB).');
            flash_redirect('error','Logo upload failed.','admin/settings.php');
        }
    }

    $setSql = "UPDATE settings SET school_name=?, school_address=?, school_phone=?, school_email=?,
               current_session=?, current_semester=?, current_marking=?, currency=?,
               currency_symbol=?, passing_grade=?";
    $params = [
        $vals['school_name'], $vals['school_address'], $vals['school_phone'], $vals['school_email'],
        $vals['current_session'], $vals['current_semester'], $vals['current_marking'],
        $vals['currency'], $vals['currency_symbol'], $vals['passing_grade']
    ];
    if ($logo !== null) {
        $setSql .= ", school_logo=?";
        $params[] = $logo;
    }
    $setSql .= " WHERE id=?";
    $params[] = $s['id'];

    $pdo->prepare($setSql)->execute($params);
    log_activity('update_settings','Updated settings');
    flash_redirect('success','Settings updated.','admin/settings.php');
}

$s = get_settings(); // refresh



     require_once __DIR__ . '/../includes/placeholder.php';
     require_once __DIR__ . '/../includes/header.php';
     require_once __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-3">System Settings</h4>
    <?= show_flash() ?>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6 class="text-uppercase small text-muted mb-3">School Information</h6>
            <div class="mb-3"><label class="form-label">School Name *</label>
                <input type="text" name="school_name" class="form-control" required value="<?= e($s['school_name']) ?>"></div>
            <div class="mb-3"><label class="form-label">Address</label>
                <input type="text" name="school_address" class="form-control" value="<?= e($s['school_address']) ?>"></div>
            <div class="mb-3"><label class="form-label">Phone</label>
                <input type="text" name="school_phone" class="form-control" value="<?= e($s['school_phone']) ?>"></div>
            <div class="mb-3"><label class="form-label">Email</label>
                <input type="email" name="school_email" class="form-control" value="<?= e($s['school_email']) ?>"></div>
            <div class="mb-3"><label class="form-label">Logo</label>
                <input type="file" name="school_logo" class="form-control" accept="image/*">
                <?php if ($s['school_logo']): ?>
                    <img src="<?= UPLOAD_URL . e($s['school_logo']) ?>" class="mt-2 rounded" style="height:60px;">
                <?php endif; ?></div>
        </div></div></div>

        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6 class="text-uppercase small text-muted mb-3">Academic & Financial</h6>
            <div class="mb-3"><label class="form-label">Current Session</label>
                <input type="text" name="current_session" class="form-control" required value="<?= e($s['current_session']) ?>"
                       placeholder="2025/2026"></div>
            <div class="mb-3"><label class="form-label">Current Semester</label>
                <select name="current_semester" class="form-select">
                    <option value="1" <?= $s['current_semester']==='1'?'selected':'' ?>>Semester 1</option>
                    <option value="2" <?= $s['current_semester']==='2'?'selected':'' ?>>Semester 2</option>
                </select></div>
            <div class="mb-3"><label class="form-label">Current Marking Period</label>
                <select name="current_marking" class="form-select">
                    <?php foreach (MARKING_PERIODS as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $s['current_marking']===$k?'selected':'' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label">Currency</label>
                    <input type="text" name="currency" class="form-control" value="<?= e($s['currency']) ?>"></div>
                <div class="col-6"><label class="form-label">Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control" value="<?= e($s['currency_symbol']) ?>"></div>
            </div>
            <div class="mt-3"><label class="form-label">Passing Grade</label>
                <select name="passing_grade" class="form-select">
                    <?php foreach (['A','B','C','D','E'] as $g): ?>
                        <option value="<?= $g ?>" <?= $s['passing_grade']===$g?'selected':'' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div></div></div>

        <div class="col-12">
            <button class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Save Settings</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>