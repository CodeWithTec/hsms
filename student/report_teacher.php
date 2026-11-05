<?php
$pageTitle = 'Report a Teacher';
$section   = 'Student · Reports';
// require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('student');
// require_once __DIR__ . '/../includes/header.php';
// require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$student = current_student();
if (!$student) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No student profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$sid = (int)$student['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $teacherId = (int)post('teacher_id');
    $subjectId = post('subject_id') ? (int)post('subject_id') : null;
    $message = post('message');
    $anonymous = post('anonymous') === '1' ? 1 : 0;
    $errs = [];
    if (!$teacherId) $errs[] = 'Select a teacher.';
    if (!$message) $errs[] = 'Message required.';

    if ($errs) {
        flash('error', implode(' ',$errs));
    } else {
        $pdo->prepare("INSERT INTO teacher_reports (student_id, teacher_id, subject_id, message, anonymous) VALUES (?,?,?,?,?)")
            ->execute([$sid, $teacherId, $subjectId, $message, $anonymous]);
        log_activity('report_teacher',"Reported teacher #$teacherId" . ($anonymous?' (anonymous)':''));
        flash_redirect('success','Report submitted. Admin will review it.','student/report_teacher.php');
    }
}

// Teachers from student's class
$teachers = [];
if ($student['class_id']) {
    $q = $pdo->prepare("SELECT DISTINCT t.id, t.first_name, t.last_name
                        FROM class_subject_teacher cst
                        JOIN teachers t ON t.id = cst.teacher_id
                        WHERE cst.class_id=? AND cst.session=?
                        ORDER BY t.first_name");
    $q->execute([$student['class_id'],$session]);
    $teachers = $q->fetchAll();
}

$subjects = [];
if ($student['class_id']) {
    $q = $pdo->prepare("SELECT sub.id, sub.name
                        FROM class_subject_teacher cst
                        JOIN subjects sub ON sub.id=cst.subject_id
                        WHERE cst.class_id=? AND cst.session=?
                        ORDER BY sub.name");
    $q->execute([$student['class_id'],$session]);
    $subjects = $q->fetchAll();
}

$mine = $pdo->prepare("SELECT r.*, t.first_name, t.last_name, sub.name AS subject_name
                       FROM teacher_reports r
                       JOIN teachers t ON t.id=r.teacher_id
                       LEFT JOIN subjects sub ON sub.id=r.subject_id
                       WHERE r.student_id=?
                       ORDER BY r.created_at DESC");
$mine->execute([$sid]);
$myReports = $mine->fetchAll();





require_once __DIR__ . '/../includes/placeholder.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';
?>
<div class="p-4">
    <h4 class="mb-3">Report a Teacher</h4>
    <p class="text-muted small">Reports go directly to the admin. You may submit anonymously.</p>
    <?= show_flash() ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Teacher *</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($teachers)): ?>
                            <small class="text-warning">No teachers assigned to your class yet.</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Related Subject (optional)</label>
                        <select name="subject_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= (int)$sub['id'] ?>"><?= e($sub['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">What happened? *</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="anonymous" value="1" id="anon" class="form-check-input">
                        <label for="anon" class="form-check-label">Submit anonymously</label>
                    </div>
                    <button class="btn btn-danger px-4">Submit Report</button>
                </form>
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card"><div class="card-header"><strong>My Past Reports</strong></div>
                <div class="card-body p-0">
                    <?php if (empty($myReports)): ?>
                        <p class="text-muted small text-center py-4 mb-0">No reports submitted.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($myReports as $r): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= e($r['first_name'].' '.$r['last_name']) ?></strong>
                                        <span class="badge bg-<?= $r['status']==='pending'?'warning':($r['status']==='reviewed'?'info':'success') ?>">
                                            <?= e($r['status']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted d-block"><?= date('M j, Y',strtotime($r['created_at'])) ?></small>
                                    <small><?= e(mb_strimwidth($r['message'],0,120,'…')) ?></small>
                                    <?php if ($r['admin_note']): ?>
                                        <div class="alert alert-info py-1 mt-2 small mb-0"><strong>Admin:</strong> <?= e($r['admin_note']) ?></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>