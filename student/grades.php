<?php
$pageTitle = 'My Grades';
$section   = 'Student · Grades';
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

$classInfo = null;
if ($student['class_id']) {
    $q = $pdo->prepare("SELECT name FROM classes WHERE id=?");
    $q->execute([$student['class_id']]);
    $classInfo = $q->fetchColumn();
}

// Subjects available for the student's class + session
$subjects = [];
if ($student['class_id']) {
    $q = $pdo->prepare("SELECT sub.id, sub.name, sub.code, t.first_name, t.last_name
                        FROM class_subject_teacher cst
                        JOIN subjects sub ON sub.id = cst.subject_id
                        JOIN teachers t ON t.id = cst.teacher_id
                        WHERE cst.class_id=? AND cst.session=?
                        ORDER BY sub.name");
    $q->execute([$student['class_id'], $session]);
    $subjects = $q->fetchAll();
}

// Load grades
$gradesData = [];
$computedData = [];
if ($subjects) {
    $subIds = implode(',', array_map('intval', array_column($subjects,'id')));
    foreach ($pdo->query("SELECT * FROM grades WHERE student_id=$sid AND session=".$pdo->quote($session)." AND subject_id IN ($subIds)")->fetchAll() as $g) {
        $gradesData[(int)$g['subject_id']][$g['marking_period']] = $g;
    }
    foreach ($pdo->query("SELECT * FROM computed_grades WHERE student_id=$sid AND session=".$pdo->quote($session)." AND subject_id IN ($subIds)")->fetchAll() as $c) {
        $computedData[(int)$c['subject_id']] = $c;
    }
}

$sem1 = ['MP1','MP2','MP3','EXAM1'];
$sem2 = ['MP4','MP5','MP6','EXAM2'];
$printMode = get('print') === '1';
?>
<div class="p-4">
    <?php if (!$printMode): ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-print-none">
        <div>
            <h4 class="mb-1">My Grades</h4>
            <small class="text-muted">
                <?= e($student['student_id']) ?> · <?= e($classInfo ?? 'No class') ?> · Session <?= e($session) ?>
            </small>
        </div>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Print Report Card</button>
    </div>
    <?php else: ?>
    <div class="text-center mb-3 d-none d-print-block">
        <h3><?= e($s['school_name']) ?></h3>
        <p class="mb-1"><?= e($s['school_address']) ?></p>
        <h5 class="mt-3">Report Card — <?= e($session) ?></h5>
    </div>
    <?php endif; ?>

    <?= show_flash() ?>

    <?php if (!$student['class_id']): ?>
        <div class="alert alert-warning">You are not assigned to a class yet.</div>
    <?php elseif (empty($subjects)): ?>
        <div class="alert alert-info">No subjects assigned to your class yet.</div>
    <?php else: ?>
        <div class="card"><div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.82rem;min-width:900px">
                <thead class="table-light">
                    <tr>
                        <th rowspan="2" style="min-width:120px">Subject</th>
                        <th colspan="5" class="text-center bg-primary text-white">Semester 1</th>
                        <th colspan="5" class="text-center bg-success text-white">Semester 2</th>
                        <th rowspan="2" class="text-center bg-dark text-white">Year<br>Avg</th>
                        <th rowspan="2" class="text-center">Grade</th>
                    </tr>
                    <tr>
                        <th class="text-center small">MP1</th>
                        <th class="text-center small">MP2</th>
                        <th class="text-center small">MP3</th>
                        <th class="text-center small">Exam</th>
                        <th class="text-center small bg-info text-white">Avg</th>
                        <th class="text-center small">MP4</th>
                        <th class="text-center small">MP5</th>
                        <th class="text-center small">MP6</th>
                        <th class="text-center small">Exam</th>
                        <th class="text-center small bg-info text-white">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subjects as $sub):
                        $sid2 = (int)$sub['id'];
                        $cg = $computedData[$sid2] ?? null;
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($sub['name']) ?></strong><br>
                                <small class="text-muted"><?= e($sub['first_name'].' '.$sub['last_name']) ?></small>
                            </td>
                            <?php foreach ($sem1 as $mp): $g = $gradesData[$sid2][$mp] ?? null; ?>
                                <td class="text-center"><?= $g && $g['score']!==null ? e($g['score']) : '—' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem1_avg']!==null ? e($cg['sem1_avg']) : '—' ?></strong></td>
                            <?php foreach ($sem2 as $mp): $g = $gradesData[$sid2][$mp] ?? null; ?>
                                <td class="text-center"><?= $g && $g['score']!==null ? e($g['score']) : '—' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem2_avg']!==null ? e($cg['sem2_avg']) : '—' ?></strong></td>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['yearly_avg']!==null ? e($cg['yearly_avg']) : '—' ?></strong></td>
                            <td class="text-center"><strong><?= $cg && $cg['grade_letter'] ? e($cg['grade_letter']) : '—' ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>

        <div class="mt-3 text-muted small">
            <strong>Grading Scale:</strong>
            A = 90–100 · B = 80–89 · C = 70–79 · D = 60–69 · E = 50–59 · F = 0–49
        </div>

        <?php if ($printMode): ?>
            <div class="mt-5 d-none d-print-block text-center">
                <div style="border-top:1px solid #333;display:inline-block;padding-top:4px;min-width:200px">Principal's Signature</div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>