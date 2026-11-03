<?php
$pageTitle = 'Manage Grades';
$section   = 'Admin · Grades';
require_once __DIR__ . '/../includes/placeholder.php';


require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];

$classId   = (int)get('class', 0);
$subjectId = (int)get('subject', 0);

// Default: first class if none chosen
if (!$classId) {
    $r = $pdo->query("SELECT id FROM classes ORDER BY grade_level, section LIMIT 1")->fetchColumn();
    $classId = (int)$r;
}
if (!$subjectId) {
    $r = $pdo->prepare("SELECT subject_id FROM class_subject_teacher WHERE class_id=? AND session=? LIMIT 1");
    $r->execute([$classId,$session]);
    $subjectId = (int)$r->fetchColumn();
}

// ============ SAVE OVERRIDE ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentId = (int)post('student_id');
    $mp        = post('marking_period');
    $scoreRaw  = post('score');
    $reason    = post('reason');
    $score     = ($scoreRaw === '') ? null : (float)$scoreRaw;

    if (!in_array($mp, array_keys(MARKING_PERIODS), true)) {
        flash_redirect('error','Invalid MP.','admin/grades_manage.php?class='.$classId.'&subject='.$subjectId);
    }
    if ($score !== null && ($score < 0 || $score > 100)) {
        flash_redirect('error','Score must be 0–100.','admin/grades_manage.php?class='.$classId.'&subject='.$subjectId);
    }

    $q = $pdo->prepare("SELECT * FROM grades WHERE student_id=? AND subject_id=? AND session=? AND marking_period=? LIMIT 1");
    $q->execute([$studentId, $subjectId, $session, $mp]);
    $existing = $q->fetch();

    $letter = score_to_letter($score);

    try {
        if ($existing) {
            $pdo->prepare("UPDATE grades SET score=?, grade_letter=?, updated_at=NOW() WHERE id=?")
                ->execute([$score, $letter, $existing['id']]);
            $gid = $existing['id'];
            $old = $existing['score'];
        } else {
            // Admin creating new grade — lock immediately? No, apply same rule
            $lockAt = date('Y-m-d H:i:s', time() + GRADE_LOCK_HOURS * 3600);
            $pdo->prepare("INSERT INTO grades (student_id,subject_id,class_id,session,marking_period,score,grade_letter,locked_at)
                           VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$studentId,$subjectId,$classId,$session,$mp,$score,$letter,$lockAt]);
            $gid = $pdo->lastInsertId();
            $old = null;
        }
        $pdo->prepare("INSERT INTO grade_audit (grade_id,student_id,subject_id,session,marking_period,old_score,new_score,changed_by,changed_by_role,reason)
                       VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$gid,$studentId,$subjectId,$session,$mp,$old,$score,user_id(),'admin',$reason]);

        recalc_student_subject($studentId, $subjectId, $session);
        log_activity('override_grade', "S#$studentId $mp=$score ($reason)");
        flash_redirect('success','Grade overridden.', 'admin/grades_manage.php?class='.$classId.'&subject='.$subjectId);
    } catch (PDOException $e) {
        flash_redirect('error','Save failed: '.$e->getMessage(), 'admin/grades_manage.php?class='.$classId.'&subject='.$subjectId);
    }
}

// Recalculate all averages for this class+subject
if (get('recalc') === '1' && $classId && $subjectId) {
    $students = $pdo->prepare("SELECT id FROM students WHERE class_id=?");
    $students->execute([$classId]);
    foreach ($students->fetchAll() as $st) {
        recalc_student_subject((int)$st['id'], $subjectId, $session);
    }
    flash_redirect('success','Averages recalculated.','admin/grades_manage.php?class='.$classId.'&subject='.$subjectId);
}

// ============ LOAD DATA ============
$classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level, section")->fetchAll();
$subjectsForClass = $pdo->prepare("SELECT DISTINCT sub.id, sub.name
                                   FROM class_subject_teacher cst
                                   JOIN subjects sub ON sub.id=cst.subject_id
                                   WHERE cst.class_id=? AND cst.session=?
                                   ORDER BY sub.name");
$subjectsForClass->execute([$classId,$session]);
$subjectsForClass = $subjectsForClass->fetchAll();

// If subjectId not in list, reset to first
if (!in_array($subjectId, array_column($subjectsForClass,'id'))) {
    $subjectId = (int)($subjectsForClass[0]['id'] ?? 0);
}

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
    $sub = (int)$subjectId;
    foreach ($pdo->query("SELECT * FROM grades WHERE subject_id=$sub AND session=$ses AND student_id IN ($ids)")->fetchAll() as $g) {
        $gradesData[(int)$g['student_id']][$g['marking_period']] = $g;
    }
    foreach ($pdo->query("SELECT * FROM computed_grades WHERE subject_id=$sub AND session=$ses AND student_id IN ($ids)")->fetchAll() as $c) {
        $computed[(int)$c['student_id']] = $c;
    }
}

$sem1 = ['MP1','MP2','MP3','EXAM1'];
$sem2 = ['MP4','MP5','MP6','EXAM2'];

// Audit log for this subject
$audit = $pdo->prepare("SELECT ga.*, st.first_name, st.last_name, u.username
                        FROM grade_audit ga
                        JOIN students st ON st.id=ga.student_id
                        LEFT JOIN users u ON u.id=ga.changed_by
                        WHERE ga.subject_id=? AND ga.session=?
                        ORDER BY ga.created_at DESC LIMIT 20");
$audit->execute([$subjectId, $session]);
$auditRows = $audit->fetchAll();
?>
<div class="col-lg-10 p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Grade Management (Admin Override)</h4>
            <small class="text-muted"><?= e($className ?? '—') ?> · <?= e($subjectName ?? '—') ?> · <?= e($session) ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?class=<?= $classId ?>&subject=<?= $subjectId ?>&recalc=1"
               class="btn btn-sm btn-outline-secondary"
               onclick="return confirm('Recalculate all averages for this class?')">
                <i class="bi bi-arrow-clockwise"></i> Recalculate
            </a>
        </div>
    </div>

    <?= show_flash() ?>

    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-5">
                <label class="form-label small">Class</label>
                <select name="class" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $classId===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small">Subject</label>
                <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php if (empty($subjectsForClass)): ?>
                        <option>No subjects assigned</option>
                    <?php else: foreach ($subjectsForClass as $sub): ?>
                        <option value="<?= (int)$sub['id'] ?>" <?= $subjectId===$sub['id']?'selected':'' ?>><?= e($sub['name']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
        </form>
    </div></div>

    <div class="alert alert-warning small py-2 mb-3">
        <i class="bi bi-shield-exclamation"></i>
        <strong>Admin override:</strong> changes here bypass the 24-hour lock. Every change must include a <strong>reason</strong> and will be logged.
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
                            <?php foreach ($sem1 as $mp): $g = $gradesData[$sid][$mp] ?? null; ?>
                                <td class="p-1 text-center">
                                    <button class="btn btn-sm btn-light border w-100 py-0" style="font-size:0.85rem"
                                            data-bs-toggle="modal" data-bs-target="#overrideModal"
                                            data-sid="<?= $sid ?>" data-mp="<?= $mp ?>"
                                            data-name="<?= e($st['first_name'].' '.$st['last_name']) ?>"
                                            data-score="<?= $g && $g['score']!==null ? e($g['score']) : '' ?>">
                                        <?= $g && $g['score']!==null ? e($g['score']) : '<span class="text-muted">—</span>' ?>
                                    </button>
                                    <?php if ($g && $g['grade_letter']): ?><small><?= e($g['grade_letter']) ?></small><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem1_avg']!==null ? e($cg['sem1_avg']) : '—' ?></strong></td>
                            <?php foreach ($sem2 as $mp): $g = $gradesData[$sid][$mp] ?? null; ?>
                                <td class="p-1 text-center">
                                    <button class="btn btn-sm btn-light border w-100 py-0" style="font-size:0.85rem"
                                            data-bs-toggle="modal" data-bs-target="#overrideModal"
                                            data-sid="<?= $sid ?>" data-mp="<?= $mp ?>"
                                            data-name="<?= e($st['first_name'].' '.$st['last_name']) ?>"
                                            data-score="<?= $g && $g['score']!==null ? e($g['score']) : '' ?>">
                                        <?= $g && $g['score']!==null ? e($g['score']) : '<span class="text-muted">—</span>' ?>
                                    </button>
                                    <?php if ($g && $g['grade_letter']): ?><small><?= e($g['grade_letter']) ?></small><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center bg-light"><strong><?= $cg && $cg['sem2_avg']!==null ? e($cg['sem2_avg']) : '—' ?></strong></td>
                            <td class="text-center bg-light">
                                <strong><?= $cg && $cg['yearly_avg']!==null ? e($cg['yearly_avg']) : '—' ?></strong>
                                <?php if ($cg && $cg['grade_letter']): ?><br><small><?= e($cg['grade_letter']) ?></small><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    <?php endif; ?>

    <?php if (!empty($auditRows)): ?>
        <div class="card mt-4">
            <div class="card-header"><strong>Recent Grade Changes</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr>
                        <th>When</th><th>Student</th><th>MP</th><th>Old</th><th>New</th><th>By</th><th>Role</th><th>Reason</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($auditRows as $a): ?>
                            <tr>
                                <td class="small text-muted text-nowrap"><?= date('M j, g:i A',strtotime($a['created_at'])) ?></td>
                                <td class="small"><?= e($a['first_name'].' '.$a['last_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($a['marking_period']) ?></span></td>
                                <td><?= $a['old_score'] !== null ? e($a['old_score']) : '—' ?></td>
                                <td><strong><?= $a['new_score'] !== null ? e($a['new_score']) : '—' ?></strong></td>
                                <td class="small"><?= e($a['username'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $a['changed_by_role']==='admin'?'danger':'primary' ?>"><?= e($a['changed_by_role']) ?></span></td>
                                <td class="small"><?= e($a['reason'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" id="overrideForm">
        <?= csrf_field() ?>
        <input type="hidden" name="student_id" id="ovSid">
        <input type="hidden" name="marking_period" id="ovMp">
        <div class="modal-header">
          <h5 class="modal-title">Override Grade</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2"><strong id="ovName">—</strong> · <span class="badge bg-secondary" id="ovMpLabel">MP1</span></p>
          <div class="mb-3">
            <label class="form-label">Score (0–100, empty to clear)</label>
            <input type="number" name="score" id="ovScore" min="0" max="100" step="0.01" class="form-control">
          </div>
          <div class="mb-0">
            <label class="form-label">Reason *</label>
            <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Corrected data-entry error"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save Override</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('overrideModal').addEventListener('show.bs.modal', e => {
    const b = e.relatedTarget;
    document.getElementById('ovSid').value = b.dataset.sid;
    document.getElementById('ovMp').value = b.dataset.mp;
    document.getElementById('ovMpLabel').textContent = b.dataset.mp;
    document.getElementById('ovName').textContent = b.dataset.name;
    document.getElementById('ovScore').value = b.dataset.score;
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>