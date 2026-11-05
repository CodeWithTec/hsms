<?php
$pageTitle = 'Teacher Reports';
$section   = 'Admin · Reports';
// require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
// require_once __DIR__ . '/../includes/header.php';
// require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'review') {
        $id = (int)post('id');
        $status = post('status');
        $note = post('admin_note');
        if (!in_array($status, ['reviewed','resolved'], true)) {
            flash('error','Invalid status.');
        } else {
            $pdo->prepare("UPDATE teacher_reports SET status=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$status, $note, user_id(), $id]);
            log_activity('review_report', "Report #$id → $status");
            flash_redirect('success',"Report marked $status.",'admin/reports.php');
        }
    }
}

$statusFilter = get('status','pending');
$where = "WHERE 1=1"; $params = [];
if (in_array($statusFilter, ['pending','reviewed','resolved'], true)) {
    $where .= " AND r.status=?";
    $params[] = $statusFilter;
}

$sql = "SELECT r.*, st.first_name AS st_fn, st.last_name AS st_ln, st.student_id,
               t.first_name AS t_fn, t.last_name AS t_ln,
               s.name AS subject_name
        FROM teacher_reports r
        JOIN students st ON st.id = r.student_id
        JOIN teachers t ON t.id = r.teacher_id
        LEFT JOIN subjects s ON s.id = r.subject_id
        $where ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();






     require_once __DIR__ . '/../includes/placeholder.php';
     require_once __DIR__ . '/../includes/header.php';
     require_once __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="p-4">
    <h4 class="mb-3">Teacher Reports</h4>
    <?= show_flash() ?>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach (['pending'=>'warning','reviewed'=>'info','resolved'=>'success','all'=>'secondary'] as $k=>$c): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm btn-<?= $statusFilter===$k?$c:'outline-'.$c ?>"><?= ucfirst($k) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($reports)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No reports found.</div></div>
    <?php else: foreach ($reports as $r): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <strong>Reported Teacher:</strong> <?= e($r['t_fn'].' '.$r['t_ln']) ?>
                        <?php if ($r['subject_name']): ?><span class="text-muted">(<?= e($r['subject_name']) ?>)</span><?php endif; ?>
                    </div>
                    <span class="badge bg-<?= $r['status']==='pending'?'warning':($r['status']==='reviewed'?'info':'success') ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </div>
                <div class="small text-muted mt-1">
                    By: <?= $r['anonymous'] ? '<em>Anonymous</em>' : e($r['st_fn'].' '.$r['st_ln'].' ('.$r['student_id'].')') ?>
                    &middot; <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
                </div>
                <p class="mt-2 mb-2"><?= nl2br(e($r['message'])) ?></p>
                <?php if ($r['admin_note']): ?>
                    <div class="alert alert-info py-2 small mb-0"><strong>Admin:</strong> <?= e($r['admin_note']) ?></div>
                <?php endif; ?>

                <?php if ($r['status'] !== 'resolved'): ?>
                    <form method="post" class="mt-3 row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="review">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <div class="col-md-8">
                            <input type="text" name="admin_note" class="form-control form-control-sm"
                                   value="<?= e($r['admin_note'] ?? '') ?>" placeholder="Action taken / note…">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button name="status" value="reviewed" class="btn btn-sm btn-info flex-fill">Mark Reviewed</button>
                            <button name="status" value="resolved" class="btn btn-sm btn-success flex-fill">Resolve</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>