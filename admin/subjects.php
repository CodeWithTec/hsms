<?php
$pageTitle = 'Manage Subjects';
$section   = 'Admin · Subjects';
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
        $code = strtoupper(post('code'));
        $name = post('name');
        $errs = [];
        if (!$code) $errs[] = 'Code required.';
        if (!$name) $errs[] = 'Name required.';
        if ($errs) {
            flash('error', implode(' ', $errs));
        } else {
            try {
                if ($id > 0) {
                    $pdo->prepare("UPDATE subjects SET code=?, name=? WHERE id=?")
                        ->execute([$code, $name, $id]);
                    flash_redirect('success','Subject updated.','admin/subjects.php');
                } else {
                    $pdo->prepare("INSERT INTO subjects (code,name) VALUES (?,?)")
                        ->execute([$code, $name]);
                    flash_redirect('success','Subject created.','admin/subjects.php');
                }
            } catch (PDOException $e) {
                flash('error','Code or name already exists.');
            }
        }
    }
    if ($fa === 'delete') {
        $id = (int)post('id');
        $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]);
        flash_redirect('success','Subject deleted.','admin/subjects.php');
    }
}

$edit = null;
if ($eid = (int)get('edit',0)) {
    $s = $pdo->prepare("SELECT * FROM subjects WHERE id=? LIMIT 1");
    $s->execute([$eid]);
    $edit = $s->fetch();
}

$subjects = $pdo->query("SELECT s.*,
                         (SELECT COUNT(*) FROM class_subject_teacher WHERE subject_id=s.id) AS teacher_count
                         FROM subjects s ORDER BY name")->fetchAll();
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-3">Subjects</h4>
    <?= show_flash() ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><strong><?= $edit?'Edit Subject':'Add Subject' ?></strong></div>
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="save">
                        <input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
                        <div class="mb-3">
                            <label class="form-label">Code *</label>
                            <input type="text" name="code" class="form-control" required
                                   maxlength="20" style="text-transform:uppercase"
                                   value="<?= e($edit['code']??'') ?>" placeholder="e.g. MATH">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?= e($edit['name']??'') ?>" placeholder="e.g. Mathematics">
                        </div>
                        <button class="btn btn-primary w-100"><?= $edit?'Update':'Create' ?></button>
                        <?php if ($edit): ?>
                            <a href="admin/subjects.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
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
                            <th>Code</th><th>Name</th><th>Teachers Assigned</th><th class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($subjects as $s): ?>
                                <tr>
                                    <td><code><?= e($s['code']) ?></code></td>
                                    <td><?= e($s['name']) ?></td>
                                    <td><span class="badge bg-info"><?= (int)$s['teacher_count'] ?></span></td>
                                    <td class="text-end">
                                        <a href="?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete subject?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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