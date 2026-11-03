<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$uid = user_id();

// Fetch student record
$st = $pdo->prepare("SELECT st.*, c.name AS class_name
                     FROM students st
                     LEFT JOIN classes c ON c.id = st.class_id
                     WHERE st.user_id=? LIMIT 1");
$st->execute([$uid]);
$student = $st->fetch();

if (!$student) {
    echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">
          No student profile linked to your account. Contact admin.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
$sid = (int)$student['id'];

// Fees summary
$fstmt = $pdo->prepare("SELECT COALESCE(SUM(amount_due),0) AS due,
                               COALESCE(SUM(amount_paid),0) AS paid
                        FROM fees WHERE student_id=? AND session=?");
$fstmt->execute([$sid, $session]);
$fees = $fstmt->fetch();
$balance = $fees['due'] - $fees['paid'];

// Recent news (top 3)
$news = $pdo->query("SELECT * FROM news ORDER BY pinned DESC, created_at DESC LIMIT 3")->fetchAll();

// Active assignments (their class)
$assignments = [];
if ($student['class_id']) {
    $astmt = $pdo->prepare("SELECT a.*, sub.name AS subject_name, t.first_name, t.last_name
                            FROM assignments a
                            JOIN subjects sub ON sub.id = a.subject_id
                            JOIN teachers t ON t.id = a.teacher_id
                            WHERE a.class_id=? AND a.status='active'
                            ORDER BY a.due_date ASC, a.created_at DESC LIMIT 5");
    $astmt->execute([$student['class_id']]);
    $assignments = $astmt->fetchAll();
}

// Pending excuses
$estmt = $pdo->prepare("SELECT COUNT(*) FROM excuses WHERE student_id=? AND status='pending'");
$estmt->execute([$sid]);
$pendingExcuses = (int)$estmt->fetchColumn();
?>
<div class="col-lg-10 p-4">
  <div class="mb-4">
    <h4 class="mb-0">Hi, <?= e($student['first_name']) ?> 👋</h4>
    <small class="text-muted"><?= e($student['student_id']) ?> &middot; <?= e($student['class_name'] ?? 'No class') ?> &middot; <?= e($session) ?></small>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="card border-start border-4 <?= $balance > 0 ? 'border-danger' : 'border-success' ?>">
        <div class="card-body">
          <small class="text-muted">Fee Balance (<?= e($session) ?>)</small>
          <h3 class="mb-0"><?= money($balance) ?></h3>
          <small class="text-muted">Paid: <?= money($fees['paid']) ?> / Due: <?= money($fees['due']) ?></small>
          <div><a href="<?= BASE_URL ?>student/fees.php" class="small">View details →</a></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card"><div class="card-body">
        <small class="text-muted">Pending Excuses</small>
        <h3 class="mb-0"><?= $pendingExcuses ?></h3>
        <a href="<?= BASE_URL ?>student/excuses.php" class="small">Submit / view →</a>
      </div></div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card"><div class="card-body">
        <small class="text-muted">Quick Links</small>
        <div class="d-grid gap-1 mt-1">
          <a href="<?= BASE_URL ?>student/grades.php" class="btn btn-sm btn-primary">My Grades</a>
          <a href="<?= BASE_URL ?>student/assignments.php" class="btn btn-sm btn-outline-primary">Assignments</a>
        </div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <strong>School News</strong>
          <a href="<?= BASE_URL ?>student/dashboard.php" class="small">Refresh</a>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($news)): ?>
              <li class="list-group-item text-muted small">No news yet.</li>
            <?php else: foreach ($news as $n): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between">
                  <strong><?php if ($n['pinned']): ?><i class="bi bi-pin-angle-fill text-warning"></i> <?php endif; ?><?= e($n['title']) ?></strong>
                  <small class="text-muted"><?= date('M j', strtotime($n['created_at'])) ?></small>
                </div>
                <small class="text-muted"><?= e(mb_strimwidth($n['content'], 0, 100, '…')) ?></small>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <strong>Upcoming Assignments</strong>
          <a href="<?= BASE_URL ?>student/assignments.php" class="small">View all</a>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($assignments)): ?>
              <li class="list-group-item text-muted small">No active assignments.</li>
            <?php else: foreach ($assignments as $a): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between">
                  <strong><?= e($a['title']) ?></strong>
                  <?php if ($a['due_date'] && strtotime($a['due_date']) < time()): ?>
                    <span class="badge bg-danger">Overdue</span>
                  <?php elseif ($a['due_date']): ?>
                    <span class="badge bg-info">Due <?= date('M j', strtotime($a['due_date'])) ?></span>
                  <?php endif; ?>
                </div>
                <small class="text-muted">
                  <?= e($a['subject_name']) ?> &middot; <?= e($a['first_name'].' '.$a['last_name']) ?>
                </small>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>