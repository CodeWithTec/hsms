<?php
$pageTitle = 'My Assignments';
$section   = 'Student · Assignments';
require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$student = current_student();
if (!$student) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No student profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$sid = (int)$student['id'];

if (!$student['class_id']) {
    echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">You are not assigned to a class yet.</div></div>';
    require_once __DIR__.'/../includes/footer.php'; exit;
}

// Mark as viewed
if ($v = (int)get('view',0)) {
    try {
        $pdo->prepare("INSERT IGNORE INTO assignment_views (assignment_id, student_id) VALUES (?,?)")
            ->execute([$v, $sid]);
    } catch (Exception $e) {}
    flash_redirect('success','Marked as seen.','student/assignments.php');
}

$list = $pdo->prepare("SELECT a.*, sub.name AS subject_name, t.first_name, t.last_name,
                       (SELECT COUNT(*) FROM assignment_views WHERE assignment_id=a.id AND student_id=?) AS seen
                       FROM assignments a
                       JOIN subjects sub ON sub.id = a.subject_id
                       JOIN teachers t ON t.id = a.teacher_id
                       WHERE a.class_id = ? AND a.status = 'active'
                       ORDER BY a.due_date IS NULL, a.due_date ASC, a.created_at DESC");
$list->execute([$sid, $student['class_id']]);
$assignments = $list->fetchAll();
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-3">My Assignments</h4>
    <?= show_flash() ?>

    <?php if (empty($assignments)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-journal-check fs-1"></i>
            <p class="mt-2 mb-0">No active assignments.</p>
        </div></div>
    <?php else: foreach ($assignments as $a):
        $overdue = $a['due_date'] && strtotime($a['due_date']) < time();
        $soon    = $a['due_date'] && !$overdue && strtotime($a['due_date']) - time() < 86400 * 2;
    ?>
        <div class="card mb-3 border-start border-4 <?= $overdue?'border-danger':($soon?'border-warning':'border-primary') ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <h6 class="mb-1"><?= e($a['title']) ?></h6>
                        <small class="text-muted">
                            <?= e($a['subject_name']) ?> · <?= e($a['first_name'].' '.$a['last_name']) ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <?php if ($overdue): ?>
                            <span class="badge bg-danger">Overdue (<?= date('M j', strtotime($a['due_date'])) ?>)</span>
                        <?php elseif ($soon): ?>
                            <span class="badge bg-warning">Due <?= date('M j', strtotime($a['due_date'])) ?></span>
                        <?php elseif ($a['due_date']): ?>
                            <span class="badge bg-info">Due <?= date('M j, Y', strtotime($a['due_date'])) ?></span>
                        <?php endif; ?>
                        <?php if ($a['seen']): ?>
                            <span class="badge bg-success ms-1"><i class="bi bi-check-lg"></i> Seen</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($a['description']): ?>
                    <p class="mb-2 small"><?= nl2br(e($a['description'])) ?></p>
                <?php endif; ?>
                <div class="d-flex gap-2 mt-2">
                    <?php if ($a['attachment']): ?>
                        <a href="<?= UPLOAD_URL . e($a['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-paperclip"></i> Download
                        </a>
                    <?php endif; ?>
                    <?php if (!$a['seen']): ?>
                        <a href="?view=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-check2"></i> Mark as Seen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>