<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'assign') {
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        $teacherId = (int)post('teacher_id');
        if (!$classId || !$subjectId || !$teacherId) {
            flash('error','All fields required.');
        } else {
            try {
                $pdo->prepare("INSERT INTO class_subject_teacher (class_id, subject_id, teacher_id, session)
                               VALUES (?,?,?,?)
                               ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id)")
                    ->execute([$classId, $subjectId, $teacherId, $session]);
                log_activity('assign_teacher', "Assigned teacher $teacherId to class $classId subj $subjectId");
                flash_redirect('success','Assignment saved.','admin/teacher_assignments.php');
            } catch (PDOException $e) {
                flash('error','Assignment failed: '.$e->getMessage());
            }
        }
    }
    if ($fa === 'unassign') {
        $id = (int)post('id');
        $pdo->prepare("DELETE FROM class_subject_teacher WHERE id=?")->execute([$id]);
        flash_redirect('success','Assignment removed.','admin/teacher_assignments.php');
    }
}

$classes  = $pdo->query("SELECT * FROM classes ORDER BY grade_level, section")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$teachers = $pdo->query("SELECT t.* FROM teachers t ORDER BY t.first_name, t.last_name")->fetchAll();

$filterClass = (int)get('class',0);

$sql = "SELECT cst.*, c.name AS class_name, s.name AS subject_name,
               t.first_name, t.last_name, t.staff_id
        FROM class_subject_teacher cst
        JOIN classes c ON c.id=cst.class_id
        JOIN subjects s ON s.id=cst.subject_id
        JOIN teachers t ON t.id=cst.teacher_id
        WHERE cst.session=?";
$params = [$session];
if ($filterClass > 0) { $sql .= " AND cst.class_id=?"; $params[] = $filterClass; }
$sql .= " ORDER BY c.grade_level, c.section, s.name";
$list = $pdo->prepare($sql);
$list->execute($params);
$assignments = $list->fetchAll();
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-1">Teacher Assignments</h4>
    <small class="text-muted d-block mb-3">Session: <?= e($session) ?> &middot; Assign teachers to class + subject combos</small>
    <?= show_flash() ?>

    <div class="card mb-3">
        <div class="card-header"><strong>Assign Teacher</strong></div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="assign">
                <div class="col-md-4">
                    <label class="form-label small">Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= (int)$sub['id'] ?>"><?= e($sub['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Teacher</label>
                    <select name="teacher_id" class="form-select" required>
                        <option value="">— Select —</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name'].' ('.$t['staff_id'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Current Assignments (<?= count($assignments) ?>)</strong>
            <form method="get" class="d-flex gap-2">
                <select name="class" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All classes</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $filterClass===$c['id']?'selected':'' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th>Class</th><th>Subject</th><th>Teacher</th><th>Staff ID</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No assignments yet.</td></tr>
                    <?php else: foreach ($assignments as $a): ?>
                        <tr>
                            <td><strong><?= e($a['class_name']) ?></strong></td>
                            <td><?= e($a['subject_name']) ?></td>
                            <td><?= e($a['first_name'].' '.$a['last_name']) ?></td>
                            <td><code><?= e($a['staff_id']) ?></code></td>
                            <td class="text-end">
                                <form method="post" class="d-inline" onsubmit="return confirm('Remove this assignment?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="unassign">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>