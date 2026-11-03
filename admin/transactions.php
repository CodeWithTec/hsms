<?php
$pageTitle = 'Transactions';
$section   = 'Admin · Income & Expenses';
require_once __DIR__ . '/../includes/placeholder.php';


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
    if ($fa === 'save') {
        $type = post('type');
        $cat = post('category');
        $amount = (float)post('amount');
        $desc = post('description');
        $date = post('transaction_date');
        if (!in_array($type,['income','expense'],true) || !$cat || $amount <= 0 || !$date) {
            flash('error','Missing or invalid fields.');
        } else {
            $pdo->prepare("INSERT INTO transactions (type,category,amount,description,transaction_date,recorded_by) VALUES (?,?,?,?,?,?)")
                ->execute([$type,$cat,$amount,$desc,$date,user_id()]);
            log_activity('add_transaction', "$type L\$$amount — $cat");
            flash_redirect('success','Transaction recorded.','admin/transactions.php');
        }
    }
    if ($fa === 'delete') {
        $pdo->prepare("DELETE FROM transactions WHERE id=?")->execute([(int)post('id')]);
        flash_redirect('success','Transaction deleted.','admin/transactions.php');
    }
}

$from = get('from', date('Y-01-01'));
$to   = get('to',   date('Y-12-31'));

$q = $pdo->prepare("SELECT t.*, u.username FROM transactions t
                    LEFT JOIN users u ON u.id=t.recorded_by
                    WHERE t.transaction_date BETWEEN ? AND ?
                    ORDER BY t.transaction_date DESC, t.id DESC");
$q->execute([$from,$to]);
$rows = $q->fetchAll();

$income = 0; $expense = 0;
foreach ($rows as $r) {
    if ($r['type']==='income') $income += (float)$r['amount'];
    else $expense += (float)$r['amount'];
}
?>
<div class="col-lg-10 p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="mb-0">Income &amp; Expenses</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#txModal">
            <i class="bi bi-plus-lg"></i> Add Transaction
        </button>
    </div>
    <?= show_flash() ?>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-start border-4 border-success"><div class="card-body">
            <small class="text-muted">Total Income</small>
            <h4 class="mb-0 text-success"><?= money($income) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-danger"><div class="card-body">
            <small class="text-muted">Total Expenses</small>
            <h4 class="mb-0 text-danger"><?= money($expense) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-primary"><div class="card-body">
            <small class="text-muted">Net Balance</small>
            <h4 class="mb-0"><?= money($income - $expense) ?></h4>
        </div></div></div>
    </div>

    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-5"><label class="form-label small">From</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
            <div class="col-md-5"><label class="form-label small">To</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-sm btn-outline-primary w-100">Filter</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Description</th>
                    <th>Recorded By</th><th></th>
                </tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td class="text-nowrap"><?= date('M j, Y', strtotime($r['transaction_date'])) ?></td>
                            <td><span class="badge bg-<?= $r['type']==='income'?'success':'danger' ?>"><?= ucfirst($r['type']) ?></span></td>
                            <td><?= e($r['category']) ?></td>
                            <td class="<?= $r['type']==='income'?'text-success':'text-danger' ?>"><?= money($r['amount']) ?></td>
                            <td class="small"><?= e($r['description'] ?? '') ?></td>
                            <td class="small text-muted"><?= e($r['username'] ?? '—') ?></td>
                            <td class="text-end">
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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

<div class="modal fade" id="txModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form_action" value="save">
      <div class="modal-header"><h5 class="modal-title">New Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label">Type *</label>
          <select name="type" class="form-select" required>
            <option value="income">Income</option>
            <option value="expense">Expense</option>
          </select></div>
        <div class="mb-3"><label class="form-label">Category *</label>
          <input type="text" name="category" class="form-control" required placeholder="e.g. Utilities, Salaries, Donation"></div>
        <div class="mb-3"><label class="form-label">Amount (LRD) *</label>
          <input type="number" name="amount" class="form-control" required min="0.01" step="0.01"></div>
        <div class="mb-3"><label class="form-label">Date *</label>
          <input type="date" name="transaction_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
        <div class="mb-0"><label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>