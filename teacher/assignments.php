<?php
$pageTitle = 'Assignments';
$section   = 'Teacher · Assignments';
require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_teacher.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$teacher = current_teacher();
if (!$teacher) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No teacher profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$tid = (int)$teacher['id'];

$action = get('action','list');
$id = (int)get('id',0);

// Load teacher's classes for dropdowns
$myCombos = $pdo->prepare("SELECT c.id AS cid, c.name AS cname, sub.id AS sid, sub.name AS sname
                           FROM class_subject_teacher cst
                           JOIN classes c ON c.id=cst.class_id
                           JOIN subjects sub ON sub.id=cst.subject_id
                           WHERE cst.teacher_id=? AND cst.session=?
                           ORDER BY c.grade_level, c.section, sub.name");
$myCombos->execute([$tid, $session]);
$myCombos = $myCombos->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $aid = (int)post('id');
        $classId = (int)post('class_id');
        $subjectId = (int)post('subject_id');
        $title = post('title');
        $desc = post('description');
        $due = post('due_date') ?: null;
        $status = post('status','active');

        // Verify teacher owns this combo
        $chk = $pdo->prepare("SELECT 1 FROM class_subject_teacher WHERE teacher_id=? AND class_id=? AND subject_id=? AND session=?");
        $chk->execute([$tid,$classId,$subjectId,$session]);
        if (!$chk->fetch()) { flash('error','Invalid class/subject combination.'); }
        elseif (!$title) { flash('error','Title required.'); }
        else {
            $attachment = null;
            if (!empty($_FILES['attachment']['name'])) {
                $attachment = upload_file('attachment','assignments', ALLOWED_DOC_TYPES);
                if ($attachment === null) { flash('error','Attachment failed (PDF/JPG/PNG, max 2MB).'); }
            }
            if (!$attachment || $attachment) {
                if ($aid > 0) {
                    $pdo->prepare("UPDATE assignments SET class_id=?, subject_id=?, title=?, description=?, due_date=?, status=?, attachment=COALESCE(?,attachment) WHERE id=? AND teacher_id=?")
                        ->execute([$classId,$subjectId,$title,$desc,$due,$status,$attachment,$aid,$tid]);
                    flash_redirect('success','Assignment updated.','teacher/assignments.php');
                } else {
                    $pdo->prepare("INSERT INTO assignments (teacher_id,class_id,subject_id,title,description,due_date,status,attachment) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$tid,$classId,$subjectId,$title,$desc,$due,$status,$attachment]);
                    log_activity('create_assignment', "New: $title");
                    flash_redirect('success','Assignment posted.','teacher/assignments.php');
                }
            }
        }
        $action = $aid > 0 ? 'edit' : 'create';
    }
    if ($fa === 'delete') {
        $pdo->prepare("DELETE FROM assignments WHERE id=? AND teacher_id=?")->execute([(int)post('id'),$tid]);
        flash_redirect('success','Assignment deleted.','teacher/assignments.php');
    }
}

$edit = null;
if ($action === 'edit' && $id > 0) {
    $e = $pdo->prepare("SELECT * FROM assignments WHERE id=? AND teacher_id=? LIMIT 1");
    $e->execute([$id,$tid]);
    $edit = $e->fetch();
    if (!$edit) flash_redirect('error','Not found.','teacher/assignments.php');
}

$list = $pdo->prepare("SELECT a.*, c.name AS class_name, sub.name AS subject_name,
                       (SELECT COUNT(*) FROM assignment_views WHERE assignment_id=a.id) AS views
                       FROM assignments a
                       JOIN classes c ON c.id=a.class_id
                       JOIN subjects sub ON sub.id=a.subject_id
                       WHERE a.teacher_id=?
                       ORDER BY a.created_at DESC");
$list->execute([$tid]);
$assignments = $list->fetchAll();
?>
<div class="col-lg-10 p-4">
<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0">Assignments</h4>
        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Assignment</a>
    </div>
    <?= show_flash() ?>
    <?php if (empty($assignments)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No assignments yet.</div></div>
    <?php else: foreach ($assignments as $a): ?>
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="mb-1"><?= e($a['title']) ?></h6>
                    <small class="text-muted">
                        <?= e($a['class_name']) ?> · <?= e($a['subject_name']) ?>
                        <?php if ($a['due_date']): ?> · Due <?= date('M j, Y',strtotime($a['due_date'])) ?><?php endif; ?>
                        · <?= (int)$a['views'] ?> views
                    </small>
                </div>
                <div>
                    <span class="badge bg-<?= $a['status']==='active'?'success':'secondary' ?>"><?= e($a['status']) ?></span>
                    <a href="?action=edit&id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary ms-1"><i class="bi bi-pencil"></i></a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php if ($a['description']): ?>
                <p class="mt-2 mb-2 small"><?= nl2br(e($a['description'])) ?></p>
            <?php endif; ?>
            <?php if ($a['attachment']): ?>
                <a href="<?= UPLOAD_URL . e($a['attachment']) ?>" target="_blank" class="small"><i class="bi bi-paperclip"></i> Attachment</a>
            <?php endif; ?>
        </div></div>
    <?php endforeach; endif; ?>

<?php else: ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0"><?= $edit?'Edit':'New' ?> Assignment</h4>
        <a href="teacher/assignments.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    <?= show_flash() ?>
    <div class="card"><div class="card-body">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Class *</label>
                    <select name="class_id" class="form-select" required>
                        <?php $seen=[]; foreach ($myCombos as $c):
                            if (in_array($c['cid'],$seen,true)) continue; $seen[]=$c['cid']; ?>
                            <option value="<?= (int)$c['cid'] ?>" <?= ($edit['class_id']??0)==$c['cid']?'selected':'' ?>><?= e($c['cname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Subject *</label>
                    <select name="subject_id" class="form-select" required>
                        <?php foreach ($myCombos as $c): ?>
                            <option value="<?= (int)$c['sid'] ?>" <?= ($edit['subject_id']??0)==$c['sid']?'selected':'' ?>><?= e($c['sname']) ?> (<?= e($c['cname']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12"><label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required value="<?= e($edit['title']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="5"><?= e($edit['description']??'') ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= e($edit['due_date']??'') ?>"></div>
                <div class="col-md-3"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['active','closed'] as $stt): ?>
                            <option value="<?= $stt ?>" <?= ($edit['status']??'active')===$stt?'selected':'' ?>><?= ucfirst($stt) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label class="form-label">Attachment</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,image/*">
                    <?php if (!empty($edit['attachment'])): ?>
                        <small><a href="<?= UPLOAD_URL.e($edit['attachment']) ?>" target="_blank">Current file</a></small>
                    <?php endif; ?></div>
                <div class="col-12">
                    <button class="btn btn-primary px-4"><?= $edit?'Update':'Post' ?></button>
                    <a href="teacher/assignments.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div></div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>