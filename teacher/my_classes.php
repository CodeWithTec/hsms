<?php
$pageTitle = 'My Classes';
$section   = 'Teacher · Classes';
require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_teacher.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$teacher = current_teacher();
if (!$teacher) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No teacher profile linked.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$tid = (int)$teacher['id'];

$cst = $pdo->prepare("SELECT cst.*, c.name AS class_name, c.grade_level,
                             sub.name AS subject_name, sub.code AS subject_code
                      FROM class_subject_teacher cst
                      JOIN classes c ON c.id = cst.class_id
                      JOIN subjects sub ON sub.id = cst.subject_id
                      WHERE cst.teacher_id = ? AND cst.session = ?
                      ORDER BY c.grade_level, c.section, sub.name");
$cst->execute([$tid, $session]);
$assignments = $cst->fetchAll();

// Fetch student rosters grouped by class
$rosters = [];
foreach ($assignments as $a) {
    if (!isset($rosters[$a['class_id']])) {
        $stmt = $pdo->prepare("SELECT st.* FROM students st WHERE st.class_id = ? ORDER BY st.last_name, st.first_name");
        $stmt->execute([$a['class_id']]);
        $rosters[$a['class_id']] = $stmt->fetchAll();
    }
}
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-1">My Classes</h4>
    <small class="text-muted d-block mb-3">Session <?= e($session) ?> · <?= count($assignments) ?> assignments</small>
    <?= show_flash() ?>

    <?php if (empty($assignments)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
            No classes assigned yet. Contact the admin.
        </div></div>
    <?php else: foreach ($assignments as $a): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong><?= e($a['class_name']) ?></strong>
                    <span class="badge bg-primary ms-2"><?= e($a['subject_name']) ?></span>
                    <span class="text-muted small ms-2">(<?= count($rosters[$a['class_id']]) ?> students)</span>
                </div>
                <a href="<?= BASE_URL ?>teacher/submit_grades.php?class=<?= (int)$a['class_id'] ?>&subject=<?= (int)$a['subject_id'] ?>"
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-clipboard-data"></i> Enter Grades
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light"><tr>
                        <th>#</th><th>Student ID</th><th>Name</th><th>Gender</th>
                    </tr></thead>
                    <tbody>
                        <?php if (empty($rosters[$a['class_id']])): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">No students in this class.</td></tr>
                        <?php else: $n=1; foreach ($rosters[$a['class_id']] as $st): ?>
                            <tr>
                                <td><?= $n++ ?></td>
                                <td><code><?= e($st['student_id']) ?></code></td>
                                <td><?= e($st['first_name'].' '.$st['last_name']) ?></td>
                                <td><?= e(ucfirst($st['gender'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>