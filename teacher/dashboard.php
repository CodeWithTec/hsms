<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('teacher');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_teacher.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$uid = user_id();

// Fetch teacher record
$tstmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$tstmt->execute([$uid]);
$teacher = $tstmt->fetch();

if (!$teacher) {
    echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">
          No teacher profile linked to your account. Contact admin.</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
$tid = (int)$teacher['id'];

// My classes & subjects
$cst = $pdo->prepare("SELECT cst.*, c.name AS class_name, sub.name AS subject_name
                      FROM class_subject_teacher cst
                      JOIN classes c ON c.id = cst.class_id
                      JOIN subjects sub ON sub.id = cst.subject_id
                      WHERE cst.teacher_id=? AND cst.session=?");
$cst->execute([$tid, $session]);
$myClasses = $cst->fetchAll();

// My assignments
$astmt = $pdo->prepare("SELECT a.*, c.name AS class_name, s.name AS subject_name
                        FROM assignments a
                        JOIN classes c ON c.id = a.class_id
                        JOIN subjects s ON s.id = a.subject_id
                        WHERE a.teacher_id=?
                        ORDER BY a.created_at DESC LIMIT 5");
$astmt->execute([$tid]);
$myAssignments = $astmt->fetchAll();

// Reports against me
$rstmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_reports WHERE teacher_id=? AND status='pending'");
$rstmt->execute([$tid]);
$pendingReports = (int)$rstmt->fetchColumn();
?>
<div class="col-lg-10 p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
      <h4 class="mb-0">Welcome, <?= e($teacher['first_name'] . ' ' . $teacher['last_name']) ?></h4>
      <small class="text-muted"><?= e($teacher['staff_id']) ?> &middot; <?= e($session) ?></small>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <small class="text-muted">My Classes</small>
        <h3 class="mb-0"><?= count($myClasses) ?></h3>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <small class="text-muted">My Assignments</small>
        <h3 class="mb-0"><?= count($myAssignments) ?></h3>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <small class="text-muted">Reports Against Me</small>
        <h3 class="mb-0 text-danger"><?= $pendingReports ?></h3>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <small class="text-muted">Quick Actions</small>
        <div class="d-grid gap-1 mt-1">
          <a href="<?= BASE_URL ?>teacher/submit_grades.php" class="btn btn-sm btn-primary">Submit Grades</a>
          <a href="<?= BASE_URL ?>teacher/assignments.php" class="btn btn-sm btn-outline-primary">New Assignment</a>
        </div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>My Classes &amp; Subjects</strong></div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($myClasses)): ?>
              <li class="list-group-item text-muted small">No classes assigned yet. Contact admin.</li>
            <?php else: foreach ($myClasses as $c): ?>
              <li class="list-group-item d-flex justify-content-between">
                <div>
                  <strong><?= e($c['class_name']) ?></strong><br>
                  <small class="text-muted"><?= e($c['subject_name']) ?></small>
                </div>
                <a href="<?= BASE_URL ?>teacher/submit_grades.php?class=<?= (int)$c['class_id'] ?>&subject=<?= (int)$c['subject_id'] ?>"
                   class="btn btn-sm btn-outline-primary align-self-center">Grades</a>
              </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between">
          <strong>Recent Assignments</strong>
          <a href="<?= BASE_URL ?>teacher/assignments.php" class="small">View all</a>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php if (empty($myAssignments)): ?>
              <li class="list-group-item text-muted small">No assignments created yet.</li>
            <?php else: foreach ($myAssignments as $a): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between">
                  <strong><?= e($a['title']) ?></strong>
                  <span class="badge bg-<?= $a['status']==='active'?'success':'secondary' ?>"><?= e($a['status']) ?></span>
                </div>
                <small class="text-muted">
                  <?= e($a['class_name']) ?> &middot; <?= e($a['subject_name']) ?>
                  <?php if ($a['due_date']): ?> &middot; Due <?= date('M j', strtotime($a['due_date'])) ?><?php endif; ?>
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