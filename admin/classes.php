<?php
$pageTitle = 'Manage Classes';
$section   = 'Admin · Classes';
require_once __DIR__ . '/../includes/placeholder.php';

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $id = (int)post('id');
        $name = post('name');
        $grade = (int)post('grade_level');
        $section = post('section');
        $errs = [];
        if (!$name) $errs[] = 'Name required.';
        if ($grade < 7 || $grade > 12) $errs[] = 'Grade must be 7–12.';
        if ($errs) {
            flash('error', implode(' ', $errs));
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare("UPDATE classes SET name=?, grade_level=?, section=? WHERE id=?")
                        ->execute([$name, $grade, $section ?: null, $id]);
                    flash_redirect('success','Class updated.','admin/classes.php');
                } else {
                    $pdo->prepare("INSERT INTO classes (name, grade_level, section) VALUES (?,?,?)")
                        ->execute([$name, $grade, $section ?: null]);
                    flash_redirect('success','Class created.','admin/classes.php');
                }
            } catch (PDOException $e) {
                flash('error', 'Name must be unique.');
            }
        }
    }
    if ($fa === 'delete') {
        $id = (int)post('id');
        $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$id]);
        flash_redirect('success','Class deleted.','admin/classes.php');
    }
}

$edit = null;
if ($eid = (int)get('edit', 0)) {
    $s = $pdo->prepare("SELECT * FROM classes WHERE id=? LIMIT 1");
    $s->execute([$eid]);
    $edit = $s->fetch();
}

$classes = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM students WHERE class_id=c.id) AS student_count
                        FROM classes c ORDER BY grade_level, section")->fetchAll();
?>
<div class="col-lg-10 p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Classes</h4>
    </div>
    <?= show_flash() ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><strong><?= $edit?'Edit Class':'Add Class' ?></strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="save">
                        <input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
                        <div class="mb-3">
                            <label class="form-label">Class Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= e($edit['name']??'') ?>" placeholder="e.g. Grade 10A">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level *</label>
                            <select name="grade_level" class="form-select" required>
                                <?php for ($i=7;$i<=12;$i++): ?>
                                    <option value="<?= $i ?>" <?= ($edit['grade_level']??0)==$i?'selected':'' ?>>Grade <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section</label>
                            <input type="text" name="section" class="form-control" maxlength="1"
                                   value="<?= e($edit['section']??'') ?>" placeholder="A, B, C…">
                        </div>
                        <button class="btn btn-primary w-100"><?= $edit?'Update':'Create' ?></button>
                        <?php if ($edit): ?>
                            <a href="admin/classes.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light"><tr>
                            <th>Name</th><th>Grade</th><th>Section</th><th>Students</th>
                            <th class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($classes as $c): ?>
                                <tr>
                                    <td><strong><?= e($c['name']) ?></strong></td>
                                    <td><?= (int)$c['grade_level'] ?></td>
                                    <td><?= e($c['section'] ?? '—') ?></td>
                                    <td><span class="badge bg-info"><?= (int)$c['student_count'] ?></span></td>
                                    <td class="text-end">
                                        <a href="?edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete class? Students will be unassigned.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>