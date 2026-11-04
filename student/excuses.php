<?php
$pageTitle = 'Submit Excuse';
$section   = 'Student · Excuses';
require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$student = current_student();
if (!$student) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No student profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$sid = (int)$student['id'];
$action = get('action','list');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $date = post('absence_date');
    $reason = post('reason');
    $errs = [];
    if (!$date) $errs[] = 'Date required.';
    if (!$reason) $errs[] = 'Reason required.';
    if ($date && strtotime($date) > time()) $errs[] = 'Date cannot be in the future.';

    $attachment = null;
    if (!empty($_FILES['attachment']['name'])) {
        $attachment = upload_file('attachment','excuses', ALLOWED_DOC_TYPES);
        if ($attachment === null) $errs[] = 'Attachment failed (PDF/JPG/PNG, max 2MB).';
    }

    if ($errs) {
        flash('error', implode(' ', $errs));
        $action = 'create';
    } else {
        $pdo->prepare("INSERT INTO excuses (student_id, absence_date, reason, attachment) VALUES (?,?,?,?)")
            ->execute([$sid, $date, $reason, $attachment]);
        log_activity('submit_excuse','Student submitted excuse');
        flash_redirect('success','Excuse submitted for review.','student/excuses.php');
    }
}

$list = $pdo->prepare("SELECT * FROM excuses WHERE student_id=? ORDER BY created_at DESC");
$list->execute([$sid]);
$excuses = $list->fetchAll();
?>
<div class="p-4">
<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0">My Excuses</h4>
        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Submit Excuse</a>
    </div>
    <?= show_flash() ?>
    <?php if (empty($excuses)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No excuses submitted.</div></div>
    <?php else: foreach ($excuses as $ex): ?>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <div>
                    <strong>Absence:</strong> <?= date('M j, Y', strtotime($ex['absence_date'])) ?>
                    <span class="badge bg-<?= $ex['status']==='pending'?'warning':($ex['status']==='approved'?'success':'danger') ?> ms-2">
                        <?= ucfirst($ex['status']) ?>
                    </span>
                </div>
                <small class="text-muted">Submitted <?= date('M j', strtotime($ex['created_at'])) ?></small>
            </div>
            <p class="mb-2"><?= nl2br(e($ex['reason'])) ?></p>
            <?php if ($ex['attachment']): ?>
                <a href="<?= UPLOAD_URL.e($ex['attachment']) ?>" target="_blank" class="small"><i class="bi bi-paperclip"></i> View attachment</a>
            <?php endif; ?>
            <?php if ($ex['admin_note']): ?>
                <div class="alert alert-info py-2 mt-2 mb-0 small"><strong>Admin note:</strong> <?= e($ex['admin_note']) ?></div>
            <?php endif; ?>
        </div></div>
    <?php endforeach; endif; ?>

<?php else: ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0">Submit Excuse</h4>
        <a href="student/excuses.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    <?= show_flash() ?>
    <div class="card"><div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Date of Absence *</label>
                <input type="date" name="absence_date" class="form-control" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Reason *</label>
                <textarea name="reason" class="form-control" rows="4" required placeholder="Explain why you missed class…"></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Supporting Document (optional)</label>
                <input type="file" name="attachment" class="form-control" accept=".pdf,image/*">
                <small class="text-muted">PDF, JPG, PNG — max 2 MB. e.g. doctor's note</small>
            </div>
            <button class="btn btn-primary px-4">Submit</button>
            <a href="student/excuses.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div></div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>