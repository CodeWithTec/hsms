<?php
$pageTitle = 'My Fees';
$section   = 'Student · Fees';
require_once __DIR__ . '/../includes/placeholder.php';




require_once __DIR__ . '/../includes/auth.php';
require_role('student');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_student.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$student = current_student();
if (!$student) { echo '<div class="col-lg-10 p-4"><div class="alert alert-warning">No student profile.</div></div>'; require_once __DIR__.'/../includes/footer.php'; exit; }
$sid = (int)$student['id'];

$fees = $pdo->prepare("SELECT * FROM fees WHERE student_id=? ORDER BY session DESC, semester DESC");
$fees->execute([$sid]);
$rows = $fees->fetchAll();

$totalDue = 0; $totalPaid = 0;
foreach ($rows as $r) { $totalDue += (float)$r['amount_due']; $totalPaid += (float)$r['amount_paid']; }
?>
<div class="p-4">
    <h4 class="mb-3">My Fees</h4>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-start border-4 border-primary"><div class="card-body">
            <small class="text-muted">Total Due</small>
            <h4 class="mb-0"><?= money($totalDue) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-success"><div class="card-body">
            <small class="text-muted">Total Paid</small>
            <h4 class="mb-0 text-success"><?= money($totalPaid) ?></h4>
        </div></div></div>
        <div class="col-md-4"><div class="card border-start border-4 border-danger"><div class="card-body">
            <small class="text-muted">Balance</small>
            <h4 class="mb-0 text-danger"><?= money($totalDue - $totalPaid) ?></h4>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Fee Records</strong></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr>
                    <th>Session</th><th>Semester</th><th>Due</th><th>Paid</th><th>Balance</th><th>Status</th><th>Due Date</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No fee records.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><code><?= e($r['session']) ?></code></td>
                            <td>Semester <?= (int)$r['semester'] ?></td>
                            <td><?= money($r['amount_due']) ?></td>
                            <td><?= money($r['amount_paid']) ?></td>
                            <td><?= money($r['balance']) ?></td>
                            <td>
                                <span class="badge bg-<?= $r['status']==='paid'?'success':($r['status']==='partial'?'warning':'danger') ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td><?= $r['due_date'] ? date('M j, Y', strtotime($r['due_date'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>