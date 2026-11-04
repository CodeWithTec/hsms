<?php
$pageTitle = 'Submit Grades';
$section   = 'Teacher · Grades';
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

$classId   = (int)get('class', 0);
$subjectId = (int)get('subject', 0);

if (!$classId || !$subjectId) {
    $first = $pdo->prepare("SELECT class_id, subject_id FROM class_subject_teacher WHERE teacher_id=? AND session=? LIMIT 1");
    $first->execute([$tid, $session]);
    if ($row = $first->fetch()) { $classId=(int)$row['class_id']; $subjectId=(int)$row['subject_id']; }
}

$verify = $pdo->prepare("SELECT 1 FROM class_subject_teacher WHERE teacher_id=? AND class_id=? AND subject_id=? AND session=?");
$verify->execute([$tid, $classId, $subjectId, $session]);
if (!$verify->fetch()) {
    echo '<div class="col-lg-10 p-4"><div class="alert alert-danger">You are not assigned to this class/subject.</div></div>';
    require_once __DIR__.'/../includes/footer.php'; exit;
}

// ============ HANDLE SAVE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentId = (int)post('student_id');
    $mp        = post('marking_period');
    $scoreRaw  = post('score');
    $score     = ($scoreRaw === '') ? null : (float)$scoreRaw;

    if (!in_array($mp, array_keys(MARKING_PERIODS), true)) {
        flash_redirect('error','Invalid MP.','teacher/submit_grades.php?class='.$classId.'&subject='.$subjectId);
    }
    if ($score !== null && ($score < 0 || $score > 100)) {
        flash_redirect('error','Score must be 0–100.','teacher/submit_grades.php?class='.$classId.'&subject='.$subjectId);
    }

    $q = $pdo->prepare("SELECT * FROM grades WHERE student_id=? AND subject_id=? AND session=? AND marking_period=? LIMIT 1");
    $q->execute([$studentId, $subjectId, $session, $mp]);
    $existing = $q->fetch();

    $canEdit = true;
    if ($existing && $existing['locked_at'] && time() > strtotime($existing['locked_at'])) {
        $canEdit = false;
    }
    if (!$canEdit) {
        flash_redirect('error','Grade is locked (>'.GRADE_LOCK_HOURS.'h). Admin must override.','teacher/submit_grades.php?class='.$classId.'&subject='.$subjectId);
    }

    $letter = score_to_letter($score);
    try {
        if ($existing) {
            $pdo->prepare("UPDATE grades SET score=?, grade_letter=?, updated_at=NOW() WHERE id=?")
                ->execute([$score, $letter, $existing['id']]);
            $gid = $existing['id'];
            $old = $existing['score'];
        } else {
            $lockAt = date('Y-m-d H:i:s', time() + GRADE_LOCK_HOURS * 3600);
            $pdo->prepare("INSERT INTO grades (student_id, subject_id, class_id, session, marking_period, score, grade_letter, entered_by, locked_at)
                           VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$studentId, $subjectId, $classId, $session, $mp, $score, $letter, $tid, $lockAt]);
            $gid = $pdo->lastInsertId();
            $old = null;
        }
        $pdo->prepare("INSERT INTO grade_audit (grade_id, student_id, subject_id, session, marking_period, old_score, new_score, changed_by, changed_by_role)
                       VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$gid, $studentId, $subjectId, $session, $mp, $old, $score, user_id(), 'teacher']);

        recalc_student_subject($studentId, $subjectId, $session);
        log_activity('update_grade', "S#$studentId $mp=$score");
        flash_redirect('success','Grade saved.','teacher/submit_grades.php?class='.$classId.'&subject='.$subjectId);
    } catch (PDOException $e) {
        flash_redirect('error','Save failed: '.$e->getMessage(),'teacher/submit_grades.php?class='.$classId.'&subject='.$subjectId);
    }
}

// ============ LOAD DATA ============
$ci = $pdo->prepare("SELECT name FROM classes WHERE id=?"); $ci->execute([$classId]);
$className = $ci->fetchColumn();
$si = $pdo->prepare("SELECT name FROM subjects WHERE id=?"); $si->execute([$subjectId]);
$subjectName = $si->fetchColumn();

$students = $pdo->prepare("SELECT st.* FROM students st WHERE st.class_id=? ORDER BY st.last_name, st.first_name");
$students->execute([$classId]);
$studentList = $students->fetchAll();

$gradesData = []; $computed = [];
if ($studentList) {
    $ids = implode(',', array_map('intval', array_column($studentList,'id')));
    $ses = $pdo->quote($session);
    foreach ($pdo->query("SELECT * FROM grades WHERE subject_id=$subjectId AND session=$ses AND student_id IN ($ids)")->fetchAll() as $g) {
        $gradesData[(int)$g['student_id']][$g['marking_period']] = $g;
    }
    foreach ($pdo->query("SELECT * FROM computed_grades WHERE subject_id=$subjectId AND session=$ses AND student_id IN ($ids)")->fetchAll() as $c) {
        $computed[(int)$c['student_id']] = $c;
    }
}

$options = $pdo->prepare("SELECT c.id AS cid, c.name AS cname, sub.id AS sid, sub.name AS sname
                          FROM class_subject_teacher cst
                          JOIN classes c ON c.id=cst.class_id
                          JOIN subjects sub ON sub.id=cst.subject_id
                          WHERE cst.teacher_id=? AND cst.session=?
                          ORDER BY c.grade_level, c.section, sub.name");
$options->execute([$tid, $session]);
$comboOptions = $options->fetchAll();

$sem1MPs = ['MP1','MP2','MP3','EXAM1'];
$sem2MPs = ['MP4','MP5','MP6','EXAM2'];
?>
<div class="p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Grade Entry</h4>
            <small class="text-muted"><?= e($className) ?> · <?= e($subjectName) ?> · <?= e($session) ?></small>
        </div>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <select name="class" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($comboOptions as $o): ?>
                    <option value="<?= (int)$o['cid'] ?>" <?= $o['cid']==$classId?'selected':'' ?>><?= e($o['cname']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <?php $seen=[]; foreach ($comboOptions as $o):
                    if ($o['cid']!=$classId || in_array($o['sid'],$seen,true)) continue; $seen[]=$o['sid']; ?>
                    <option value="<?= (int)$o['sid'] ?>" <?= $o['sid']==$subjectId?'selected':'' ?>><?= e($o['sname']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?= show_flash() ?>
    <div class="alert alert-info small py-2 mb-3">
        <i class="bi bi-info-circle"></i> Each grade can be edited for <strong><?= GRADE_LOCK_HOURS ?> hours</strong> after first save. Locked cells (🔒) require admin override.
    </div>

    <?php if (empty($studentList)): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No students in this class.</div></div>
    <?php else: ?>
        <div class="card"><div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0" style="font-size:0.82rem;min-width:1050px">
                <thead class="table-light">
                    <tr>
                        <th rowspan="2" style="min-width:150px">Student</th>
                        <th colspan="5" class="text-center bg-primary text-white">Semester 1</th>
                        <th colspan="5" class="text-center bg-success text-white">Semester 2</th>
                        <th rowspan="2" class="text-center bg-dark text-white">Yearly</th>
                    </tr>
                    <tr>
                        <th class="text-center">MP1</th><th class="text-center">MP2</th><th class="text-center">MP3</th><th class="text-center">Exam</th>
                        <th class="text-center bg-info text-white">Avg</th>
                        <th class="text-center">MP4</th><th class="text-center">MP5</th><th class="text-center">MP6</th><th class="text-center">Exam</th>
                        <th class="text-center bg-info text-white">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($studentList as $st):
                        $sid = (int)$st['id']; $cg = $computed[$sid] ?? null; ?>
                        <tr>
                            <td>
                                <strong><?= e($st['last_name'].', '.$st['first_name']) ?></strong><br>
                                <small class="text-muted"><?= e($st['student_id']) ?></small>
                            </td>

                            <?php /* Semester 1 cells */ ?>
                            <?php foreach ($sem1MPs as $mp):
                                $g = $gradesData[$sid][$mp] ?? null;
                                $locked = $g && $g['locked_at'] && time() > strtotime($g['locked_at']);
                            ?>
                                <td class="p-1 text-center">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= $sid ?>">
                                        <input type="hidden" name="marking_period" value="<?= $mp ?>">
                                        <input type="number" name="score" min="0" max="100" step="0.01"
                                               class="form-control form-control-sm text-center p-1" style="width:60px;margin:auto"
                                               value="<?= $g && $g['score']!==null ? e($g['score']) : '' ?>"
                                               <?= $locked?'readonly':'' ?>
                                               <?= $locked?'':'' ?>
                                               onchange="this.form.submit()">
                                    </form>
                                    <?php if ($g && $g['score']!==null): ?>
                                        <small class="text-<?= $locked?'danger':'success' ?>"><?= e($g['grade_letter']) ?><?= $locked?' 🔒':'' ?></small>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem1_avg']!==null ? e($cg['sem1_avg']) : '—' ?></strong></td>

                            <?php /* Semester 2 cells */ ?>
                            <?php foreach ($sem2MPs as $mp):
                                $g = $gradesData[$sid][$mp] ?? null;
                                $locked = $g && $g['locked_at'] && time() > strtotime($g['locked_at']);
                            ?>
                                <td class="p-1 text-center">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="student_id" value="<?= $sid ?>">
                                        <input type="hidden" name="marking_period" value="<?= $mp ?>">
                                        <input type="number" name="score" min="0" max="100" step="0.01"
                                               class="form-control form-control-sm text-center p-1" style="width:60px;margin:auto"
                                               value="<?= $g && $g['score']!==null ? e($g['score']) : '' ?>"
                                               <?= $locked?'readonly':'' ?>
                                               onchange="this.form.submit()">
                                    </form>
                                    <?php if ($g && $g['score']!==null): ?>
                                        <small class="text-<?= $locked?'danger':'success' ?>"><?= e($g['grade_letter']) ?><?= $locked?' 🔒':'' ?></small>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem2_avg']!==null ? e($cg['sem2_avg']) : '—' ?></strong></td>

                            <td class="text-center bg-light"><strong><?= $cg && $cg['yearly_avg']!==null ? e($cg['yearly_avg']) : '—' ?>
                                <?php if ($cg && $cg['grade_letter']): ?><br><small><?= e($cg['grade_letter']) ?></small><?php endif; ?>
                            </strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
        <p class="text-muted small mt-2 mb-0">Values save automatically when you leave a cell. 🔒 = locked.</p>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>