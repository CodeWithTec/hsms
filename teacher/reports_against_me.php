<?php
$pageTitle = 'Reports Against Me';
$section   = 'Teacher · Reports';
require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_teacher.php';

global $pdo;
$teacher = current_teacher();
if (!$teacher) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No teacher profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$tid = (int)$teacher['id'];

$list = $pdo->prepare("SELECT r.*, st.first_name AS fn, st.last_name AS ln, sub.name AS subject_name
                       FROM teacher_reports r
                       JOIN students st ON st.id = r.student_id
                       LEFT JOIN subjects sub ON sub.id = r.subject_id
                       WHERE r.teacher_id=?
                       ORDER BY r.created_at DESC");
$list->execute([$tid]);
$reports = $list->fetchAll();
?>
<div class="p-4">
    <h4 class="mb-3">Reports Against Me</h4>
    <p class="text-muted small">Complaints students have submitted about you. Reports marked anonymous hide the student identity.</p>
    <?php if (empty($reports)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle text-success fs-1"></i>
            <p class="mt-2 mb-0">No reports against you.</p>
        </div></div>
    <?php else: foreach ($reports as $r): ?>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                <span class="badge bg-<?= $r['status']==='pending'?'warning':($r['status']==='reviewed'?'info':'success') ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
                <small class="text-muted"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></small>
            </div>
            <p class="mb-2"><?= nl2br(e($r['message'])) ?></p>
            <small class="text-muted">
                By: <?= $r['anonymous'] ? '<em>Anonymous</em>' : e($r['fn'].' '.$r['ln']) ?>
                <?php if ($r['subject_name']): ?> · Subject: <?= e($r['subject_name']) ?><?php endif; ?>
            </small>
            <?php if ($r['admin_note']): ?>
                <div class="alert alert-info py-2 mt-2 mb-0 small"><strong>Admin:</strong> <?= e($r['admin_note']) ?></div>
            <?php endif; ?>
        </div></div>
    <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>