<?php
$pageTitle = 'Fees';
$section   = 'Admin · Fees';
require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$action = get('action','list');

// ---- CREATE / UPDATE FEE RECORD ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save_fee') {
        $sid = (int)post('student_id');
        $sess = post('session');
        $sem = post('semester');
        $due = (float)post('amount_due');
        $dueDate = post('due_date') ?: null;

        if (!$sid || !$sess || !in_array($sem,['1','2'])) {
            flash_redirect('error','Missing fields.','admin/fees.php');
        }
        try {
            $pdo->prepare("INSERT INTO fees (student_id,session,semester,amount_due,due_date,recorded_by)
                           VALUES (?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE amount_due=VALUES(amount_due), due_date=VALUES(due_date)")
                ->execute([$sid,$sess,$sem,$due,$dueDate,user_id()]);
            log_activity('save_fee', "Fee set for student #$sid ($sess sem $sem)");
            flash_redirect('success','Fee record saved.','admin/fees.php');
        } catch (PDOException $e) {
            flash_redirect('error','Failed: '.$e->getMessage(),'admin/fees.php');
        }
    }
    if ($fa === 'record_payment') {
        $feeId = (int)post('fee_id');
        $amount = (float)post('amount');
        if ($feeId <= 0 || $amount <= 0) {
            flash_redirect('error','Invalid payment.','admin/fees.php');
        }
        try {
            $pdo->beginTransaction();
            $f = $pdo->prepare("SELECT * FROM fees WHERE id=? FOR UPDATE");
            $f->execute([$feeId]);
            $fee = $f->fetch();
            if (!$fee) throw new Exception('Fee record not found.');

            $newPaid = (float)$fee['amount_paid'] + $amount;
            $status = 'unpaid';
            if ($newPaid >= (float)$fee['amount_due']) $status = 'paid';
            elseif ($newPaid > 0) $status = 'partial';

            $pdo->prepare("UPDATE fees SET amount_paid=?, status=? WHERE id=?")
                ->execute([$newPaid, $status, $feeId]);
            $pdo->commit();
            log_activity('record_payment', "Paid L\$$amount on fee #$feeId");
            flash_redirect('success',"Payment of " . money($amount) . " recorded.",'admin/fees.php');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash_redirect('error','Failed: '.$e->getMessage(),'admin/fees.php');
        }
    }
    if ($fa === 'delete') {
        $pdo->prepare("DELETE FROM fees WHERE id=?")->execute([(int)post('id')]);
        flash_redirect('success','Fee record deleted.','admin/fees.php');
    }
}

// ---- LOAD ----
$filterSession = get('fsess',$session);
$filterStatus = get('status','');

$where = "WHERE f.session=?"; $params = [$filterSession];
if (in_array($filterStatus, ['unpaid','partial','paid'], true)) {
    $where .= " AND f.status=?";
    $params[] = $filterStatus;
}

$q = $pdo->prepare("SELECT f.*, st.first_name, st.last_name, st.student_id AS st_code, c.name AS class_name
                    FROM fees f
                    JOIN students st ON st.id = f.student_id
                    LEFT JOIN classes c ON c.id = st.class_id
                    $where ORDER BY st.last_name, st.first_name, f.semester");
$q->execute($params);
$fees = $q->fetchAll();

// Totals
$totals = $pdo->prepare("SELECT COALESCE(SUM(amount_due),0) AS due, COALESCE(SUM(amount_paid),0) AS paid
                         FROM fees WHERE session=?");
$totals->execute([$filterSession]);
$T = $totals->fetch();

// For "add fee" form
$allStudents = $pdo->query("SELECT id, first_name, last_name, student_id FROM students ORDER BY last_name")->fetchAll();
?>
<div class="col-lg-10 p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="mb-0">Fees</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
            <i class="bi bi-plus-lg"></i> Add Fee Record
        </button>
    </div>

    <?= show_flash() ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-start border-4 border-primary"><div class="card-body">
            <small class="text-muted">Total Due (<?= e($filterSession) ?>)</small>
            <h4 class="mb-0"><?= money($T['due']) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-success"><div class="card-body">
            <small class="text-muted">Total Paid</small>
            <h4 class="mb-0 text-success"><?= money($T['paid']) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-danger"><div class="card-body">
            <small class="text-muted">Outstanding</small>
            <h4 class="mb-0 text-danger"><?= money($T['due'] - $T['paid']) ?></h4>
        </div></div></div>
    </div>

    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Session</label>
                <input type="text" name="fsess" class="form-control form-control-sm" value="<?= e($filterSession) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['unpaid','partial','paid'] as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-sm btn-outline-primary w-100">Filter</button>
            </div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th>Student</th><th>Class</th><th>Sem</th><th>Due</th><th>Paid</th><th>Balance</th>
                    <th>Status</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($fees)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No fee records.</td></tr>
                    <?php else: foreach ($fees as $f): ?>
                        <tr>
                            <td><strong><?= e($f['first_name'].' '.$f['last_name']) ?></strong><br>
                                <small class="text-muted"><?= e($f['st_code']) ?></small></td>
                            <td><?= e($f['class_name'] ?? '—') ?></td>
                            <td><?= (int)$f['semester'] ?></td>
                            <td><?= money($f['amount_due']) ?></td>
                            <td class="text-success"><?= money($f['amount_paid']) ?></td>
                            <td class="text-danger fw-bold"><?= money($f['balance']) ?></td>
                            <td><span class="badge bg-<?= $f['status']==='paid'?'success':($f['status']==='partial'?'warning':'danger') ?>">
                                <?= ucfirst($f['status']) ?></span></td>
                            <td class="text-end">
                                <?php if ($f['status'] !== 'paid'): ?>
                                    <button class="btn btn-sm btn-success"
                                            data-bs-toggle="modal" data-bs-target="#payModal"
                                            data-id="<?= (int)$f['id'] ?>"
                                            data-balance="<?= e($f['balance']) ?>"
                                            data-name="<?= e($f['first_name'].' '.$f['last_name']) ?>">
                                        <i class="bi bi-cash"></i> Pay
                                    </button>
                                <?php endif; ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this fee record?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Fee Modal -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="save_fee">
      <div class="modal-header"><h5 class="modal-title">Add / Update Fee Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Student *</label>
          <select name="student_id" class="form-select" required>
            <option value="">—</option>
            <?php foreach ($allStudents as $st): ?>
              <option value="<?= (int)$st['id'] ?>"><?= e($st['first_name'].' '.$st['last_name'].' — '.$st['student_id']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Session *</label>
            <input type="text" name="session" class="form-control" required value="<?= e($session) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Semester *</label>
            <select name="semester" class="form-select" required>
              <option value="1">Semester 1</option>
              <option value="2">Semester 2</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Amount Due (LRD) *</label>
          <input type="number" name="amount_due" class="form-control" required min="0" step="0.01">
        </div>
        <div class="mb-0">
          <label class="form-label">Due Date</label>
          <input type="date" name="due_date" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="record_payment">
      <input type="hidden" name="fee_id" id="payFeeId">
      <div class="modal-header"><h5 class="modal-title">Record Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="mb-2">Student: <strong id="payName"></strong></p>
        <p class="mb-3">Outstanding: <strong class="text-danger" id="payBal"></strong></p>
        <label class="form-label">Amount (LRD) *</label>
        <input type="number" name="amount" class="form-control" required min="0.01" step="0.01">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success">Record Payment</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.getElementById('payModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('payFeeId').value = b.dataset.id;
    document.getElementById('payName').textContent = b.dataset.name;
    document.getElementById('payBal').textContent = 'L$ ' + parseFloat(b.dataset.balance).toFixed(2);
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>