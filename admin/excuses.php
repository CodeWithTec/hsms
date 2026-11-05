<?php
$pageTitle = 'Excuse Requests';
$section   = 'Admin · Excuses';
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
        if (!in_array($status, ['approved','rejected'], true)) {
            flash('error','Invalid status.');
        } else {
            $pdo->prepare("UPDATE excuses SET status=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
                ->execute([$status, $note, user_id(), $id]);
            log_activity('review_excuse', "Excuse #$id → $status");
            flash_redirect('success',"Excuse $status.",'admin/excuses.php');
        }
    }
}

$statusFilter = get('status','pending');
$where = "WHERE 1=1";
$params = [];
if (in_array($statusFilter, ['pending','approved','rejected'], true)) {
    $where .= " AND e.status=?";
    $params[] = $statusFilter;
}

$sql = "SELECT e.*, st.first_name, st.last_name, st.student_id, u.username
        FROM excuses e
        JOIN students st ON st.id = e.student_id
        JOIN users u ON u.id = st.user_id
        $where ORDER BY e.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$excuses = $stmt->fetchAll();




     require_once __DIR__ . '/../includes/placeholder.php';
     require_once __DIR__ . '/../includes/header.php';
     require_once __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class=" p-4">
    <h4 class="mb-3">Excuse Requests</h4>
    <?= show_flash() ?>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach (['pending'=>'warning','approved'=>'success','rejected'=>'danger','all'=>'secondary'] as $k=>$c): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm btn-<?= $statusFilter===$k?$c:'outline-'.$c ?>">
                <?= ucfirst($k) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($excuses)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No excuses found.</div></div>
    <?php else: foreach ($excuses as $ex): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <strong><?= e($ex['first_name'].' '.$ex['last_name']) ?></strong>
                        <code class="ms-2"><?= e($ex['student_id']) ?></code>
                        <span class="badge bg-<?= $ex['status']==='pending'?'warning':($ex['status']==='approved'?'success':'danger') ?> ms-2">
                            <?= ucfirst($ex['status']) ?>
                        </span>
                    </div>
                    <small class="text-muted">Absence: <?= date('M j, Y', strtotime($ex['absence_date'])) ?></small>
                </div>
                <p class="mb-2 mt-2"><?= nl2br(e($ex['reason'])) ?></p>
                <?php if ($ex['attachment']): ?>
                    <a href="<?= UPLOAD_URL . e($ex['attachment']) ?>" target="_blank" class="small">
                        <i class="bi bi-paperclip"></i> View attachment
                    </a>
                <?php endif; ?>
                <?php if ($ex['admin_note']): ?>
                    <div class="alert alert-info py-2 mt-2 small mb-0">
                        <strong>Admin note:</strong> <?= e($ex['admin_note']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($ex['status'] === 'pending'): ?>
                    <form method="post" class="mt-3 row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="form_action" value="review">
                        <input type="hidden" name="id" value="<?= (int)$ex['id'] ?>">
                        <div class="col-md-8">
                            <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Optional note…">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button name="status" value="approved" class="btn btn-sm btn-success flex-fill">Approve</button>
                            <button name="status" value="rejected" class="btn btn-sm btn-danger flex-fill">Reject</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>