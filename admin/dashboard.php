<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];

// --- Stats ---
$counts = [
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'teachers' => (int)$pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
    'classes'  => (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'subjects' => (int)$pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM excuses WHERE status='pending'");
$stmt->execute();
$pendingExcuses = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_reports WHERE status='pending'");
$stmt->execute();
$pendingReports = (int)$stmt->fetchColumn();

// Total fees collected this session
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM fees WHERE session=?");
$stmt->execute([$session]);
$feesCollected = (float)$stmt->fetchColumn();

// Total outstanding
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_due-amount_paid),0) FROM fees WHERE session=?");
$stmt->execute([$session]);
$feesOutstanding = (float)$stmt->fetchColumn();

// Recent news
$news = $pdo->query("SELECT * FROM news ORDER BY pinned DESC, created_at DESC LIMIT 5")->fetchAll();

// Recent activity
$logs = $pdo->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 8")->fetchAll();
?>
<div class="col-lg-10 margin-left-250 p-2 dashboard">
  <?= show_flash() ?>
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 top-0">
    <div>
      <h4 class="mb-0">Admin Dashboard</h4>
      <small class="text-muted"><?= e($session) ?> &middot; Semester <?= e($s['current_semester']) ?> &middot; <?= e(MARKING_PERIODS[$s['current_marking']]) ?></small>
    </div>
    <span class="badge bg-primary">Welcome, <?= e(current_user()['username']) ?></span>
  </div>

  <!-- Top stat cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card h-100"><div class="card-body">
        <small class="text-muted">Students</small>
        <div class="d-flex justify-content-between align-items-end">
          <h3 class="mb-0"><?= $counts['students'] ?></h3>
          <i class="bi bi-people fs-3 text-primary"></i>
        </div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100"><div class="card-body">
        <small class="text-muted">Teachers</small>
        <div class="d-flex justify-content-between align-items-end">
          <h3 class="mb-0"><?= $counts['teachers'] ?></h3>
          <i class="bi bi-person-badge fs-3 text-success"></i>
        </div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100"><div class="card-body">
        <small class="text-muted">Classes</small>
        <div class="d-flex justify-content-between align-items-end">
          <h3 class="mb-0"><?= $counts['classes'] ?></h3>
          <i class="bi bi-building fs-3 text-info"></i>
        </div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card h-100"><div class="card-body">
        <small class="text-muted">Subjects</small>
        <div class="d-flex justify-content-between align-items-end">
          <h3 class="mb-0"><?= $counts['subjects'] ?></h3>
          <i class="bi bi-book fs-3 text-warning"></i>
        </div>
      </div></div>
    </div>
  </div>

  <!-- Alerts row -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-warning"><div class="card-body">
        <small class="text-muted">Pending Excuses</small>
        <h3 class="mb-0"><?= $pendingExcuses ?></h3>
        <a href="<?= BASE_URL ?>admin/excuses.php" class="small">Review →</a>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-danger"><div class="card-body">
        <small class="text-muted">Pending Teacher Reports</small>
        <h3 class="mb-0"><?= $pendingReports ?></h3>
        <a href="<?= BASE_URL ?>admin/reports.php" class="small">Review →</a>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 border-start border-4 border-success"><div class="card-body">
        <small class="text-muted">Fees Collected (<?= e($session) ?>)</small>
        <h3 class="mb-0"><?= money($feesCollected) ?></h3>
        <small class="text-danger">Outstanding: <?= money($feesOutstanding) ?></small>
      </div></div>
    </div>
  </div>

  <!-- Financial summary -->
<div class="row g-3 mt-2">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Financial Summary (<?= e($session) ?>)</strong></div>
            <div class="card-body">
                <?php
                $inc = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income' AND YEAR(transaction_date)=?");
                $inc->execute([substr($session,0,4)]);
                $totalIncome = (float)$inc->fetchColumn();

                $exp = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='expense' AND YEAR(transaction_date)=?");
                $exp->execute([substr($session,0,4)]);
                $totalExpense = (float)$exp->fetchColumn();
                ?>
                <table class="table table-sm mb-0">
                    <tr><td>Fees Collected</td><td class="text-end text-success"><?= money($feesCollected) ?></td></tr>
                    <tr><td>Other Income</td><td class="text-end text-success"><?= money($totalIncome) ?></td></tr>
                    <tr><td>Expenses</td><td class="text-end text-danger"><?= money($totalExpense) ?></td></tr>
                    <tr class="table-light"><td><strong>Net (Fees + Income − Expense)</strong></td>
                        <td class="text-end"><strong><?= money($feesCollected + $totalIncome - $totalExpense) ?></strong></td></tr>
                    <tr><td>Outstanding Fees</td><td class="text-end text-danger"><?= money($feesOutstanding) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><strong>Quick Actions</strong></div>
            <div class="card-body d-grid gap-2">
                <a href="<?= BASE_URL ?>admin/students.php?action=create" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus"></i> Add Student</a>
                <a href="<?= BASE_URL ?>admin/teachers.php?action=create" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-badge"></i> Add Teacher</a>
                <a href="<?= BASE_URL ?>admin/fees.php" class="btn btn-sm btn-outline-success"><i class="bi bi-cash-coin"></i> Record Payment</a>
                <a href="<?= BASE_URL ?>admin/grades_manage.php" class="btn btn-sm btn-outline-warning"><i class="bi bi-clipboard-data"></i> Grade Override</a>
                <a href="<?= BASE_URL ?>admin/news.php?action=create" class="btn btn-sm btn-outline-info"><i class="bi bi-newspaper"></i> Post News</a>
            </div>
        </div>
    </div>
</div>

  <div class="row g-3">
    <!-- Recent news -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <strong>Recent News</strong>
          <a href="<?= BASE_URL ?>admin/news.php" class="small">View all</a>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($news)): ?>
              <li class="list-group-item text-muted small">No news posted yet.</li>
            <?php else: foreach ($news as $n): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between">
                  <div>
                    <?php if ($n['pinned']): ?><i class="bi bi-pin-angle-fill text-warning"></i><?php endif; ?>
                    <strong><?= e($n['title']) ?></strong>
                  </div>
                  <small class="text-muted"><?= date('M j', strtotime($n['created_at'])) ?></small>
                </div>
                <small class="text-muted"><?= e(mb_strimwidth($n['content'], 0, 90, '…')) ?></small>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>

    <!-- Recent activity -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <strong>Recent Activity</strong>
          <a href="<?= BASE_URL ?>admin/activity_log.php" class="small">View all</a>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($logs)): ?>
              <li class="list-group-item text-muted small">No activity yet.</li>
            <?php else: foreach ($logs as $l): ?>
              <li class="list-group-item small">
                <span class="badge bg-secondary"><?= e($l['action']) ?></span>
                <?= e($l['details']) ?>
                <div class="text-muted" style="font-size:0.75rem;"><?= date('M j, g:i A', strtotime($l['created_at'])) ?></div>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>