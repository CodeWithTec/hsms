<?php
$pageTitle = 'Manage Students';
$section   = 'Admin · Students';
require_once __DIR__ . '/../includes/placeholder.php';

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$s = get_settings();
$session = $s['current_session'];
$action = get('action', 'list');
$id = (int)get('id', 0);

// ============ HANDLE POST ACTIONS ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = post('form_action');

    // ---- DELETE ----
    if ($formAction === 'delete') {
        $delId = (int)post('id');
        $stmt = $pdo->prepare("SELECT u.id AS uid, st.student_id FROM students st
                               JOIN users u ON u.id = st.user_id
                               WHERE st.id=?");
        $stmt->execute([$delId]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$row['uid']]);
            log_activity('delete_student', 'Deleted ' . $row['student_id']);
            flash_redirect('success', 'Student deleted.', 'admin/students.php');
        }
        flash_redirect('error', 'Student not found.', 'admin/students.php');
    }

    // ---- CREATE or UPDATE ----
    if ($formAction === 'save') {
        $errors = [];
        $firstName  = post('first_name');
        $lastName   = post('last_name');
        $gender     = post('gender');
        $dob        = post('dob') ?: null;
        $classId    = post('class_id') ? (int)post('class_id') : null;
        $phone      = post('phone');
        $address    = post('address');
        $county     = post('county');
        $guardian   = post('guardian_name');
        $gPhone     = post('guardian_phone');
        $email      = post('email');
        $username   = post('username');
        $password   = post('password');
        $status     = post('status', 'active');

        if ($firstName === '') $errors[] = 'First name required.';
        if ($lastName  === '') $errors[] = 'Last name required.';
        if ($email     === '') $errors[] = 'Email required.';
        if ($username  === '') $errors[] = 'Username required.';

        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = upload_file('photo', 'students', ALLOWED_IMAGE_TYPES);
            if ($photo === null) $errors[] = 'Photo upload failed (must be JPG/PNG/GIF/WebP, max 2MB).';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            try {
                if ($id > 0) {
                    // UPDATE
                    $stmt = $pdo->prepare("SELECT user_id, photo FROM students WHERE id=?");
                    $stmt->execute([$id]);
                    $existing = $stmt->fetch();
                    if (!$existing) {
                        flash_redirect('error', 'Student not found.', 'admin/students.php');
                    }
                    // Update users row
                    $pdo->prepare("UPDATE users SET username=?, email=?, status=? WHERE id=?")
                        ->execute([$username, $email, $status, $existing['user_id']]);
                    if ($password !== '') {
                        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $existing['user_id']]);
                    }
                    // Update student row
                    if ($photo === null) $photo = $existing['photo'];
                    $pdo->prepare("UPDATE students SET first_name=?, last_name=?, gender=?, dob=?,
                                   class_id=?, phone=?, address=?, county=?, guardian_name=?,
                                   guardian_phone=?, photo=? WHERE id=?")
                        ->execute([$firstName, $lastName, $gender, $dob, $classId, $phone,
                                   $address, $county, $guardian, $gPhone, $photo, $id]);
                    log_activity('update_student', "Updated student #$id");
                    flash_redirect('success', 'Student updated.', 'admin/students.php');
                } else {
                    // CREATE
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO users (username,email,password_hash,role,status,must_change_password)
                                    VALUES (?,?,?,?,?,1)")
                        ->execute([$username, $email,
                                password_hash($password !== '' ? $password : 'Student@123', PASSWORD_DEFAULT),
                                'student', $status]);
                    $newUserId = (int)$pdo->lastInsertId();
                    $studentId = next_student_id();
                    $pdo->prepare("INSERT INTO students
                                   (user_id, student_id, first_name, last_name, gender, dob,
                                    class_id, phone, address, county, guardian_name,
                                    guardian_phone, photo, enrolled_at)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE())")
                        ->execute([$newUserId, $studentId, $firstName, $lastName, $gender, $dob,
                                   $classId, $phone, $address, $county, $guardian, $gPhone, $photo]);
                    $pdo->commit();
                    log_activity('create_student', "Created $studentId");
                    flash_redirect('success', "Student created. ID: $studentId | Default password: " . ($password ?: 'Student@123'), 'admin/students.php');
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $msg = strpos($e->getMessage(), 'Duplicate') !== false
                    ? 'Username or email already exists.'
                    : 'Database error: ' . $e->getMessage();
                flash('error', $msg);
            }
        }
        // on error, fall through to show form with data
        $action = $id > 0 ? 'edit' : 'create';
    }
}

// ============ FETCH DATA FOR VIEWS ============
$classes = $pdo->query("SELECT * FROM classes ORDER BY grade_level, section")->fetchAll();
$counties = LIBERIA_COUNTIES;

// For create/edit form
$editStudent = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT st.*, u.username, u.email, u.status
                           FROM students st JOIN users u ON u.id=st.user_id
                           WHERE st.id=? LIMIT 1");
    $stmt->execute([$id]);
    $editStudent = $stmt->fetch();
    if (!$editStudent) {
        flash_redirect('error', 'Student not found.', 'admin/students.php');
    }
}

// For list view
if ($action === 'list') {
    $search = get('q');
    $classFilter = (int)get('class', 0);
    $page = max(1, (int)get('page', 1));
    $perPage = 15;

    $where = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (st.first_name LIKE ? OR st.last_name LIKE ? OR st.student_id LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($classFilter > 0) {
        $where .= " AND st.class_id = ?";
        $params[] = $classFilter;
    }

    $total = $pdo->prepare("SELECT COUNT(*) FROM students st $where");
    $total->execute($params);
    $pg = paginate((int)$total->fetchColumn(), $perPage, $page);

    $sql = "SELECT st.*, c.name AS class_name
            FROM students st
            LEFT JOIN classes c ON c.id = st.class_id
            $where
            ORDER BY st.created_at DESC
            LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
    $list = $pdo->prepare($sql);
    $list->execute($params);
    $students = $list->fetchAll();
}
?>
<div class="dashboard">
<div class="col-lg-10 p-4">

<?php if ($action === 'list'): ?>
    <!-- ========================================================= -->
    <!-- LIST VIEW                                                  -->
    <!-- ========================================================= -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Students</h4>
            <small class="text-muted"><?= $pg['total'] ?> total</small>
        </div>
        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Student</a>
    </div>

    <?= show_flash() ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search name or ID…">
                </div>
                <div class="col-md-4">
                    <select name="class" class="form-select">
                        <option value="">All classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $classFilter===$c['id']?'selected':'' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Photo</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Gender</th>
                        <th>County</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No students yet.</td></tr>
                    <?php else: foreach ($students as $st): ?>
                        <tr>
                            <td>
                                <?php if ($st['photo']): ?>
                                    <img src="<?= UPLOAD_URL . e($st['photo']) ?>" class="rounded-circle" style="width:38px;height:38px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:0.8rem;">
                                        <?= strtoupper(substr($st['first_name'],0,1) . substr($st['last_name'],0,1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($st['student_id']) ?></code></td>
                            <td><?= e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                            <td><?= e($st['class_name'] ?? '—') ?></td>
                            <td><?= e(ucfirst($st['gender'] ?? '—')) ?></td>
                            <td><?= e($st['county'] ?? '—') ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?= (int)$st['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this student? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$st['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pg['pages'] > 1): ?>
            <div class="card-footer">
                <nav><ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
                        <li class="page-item <?= $i===$pg['page']?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&class=<?= $classFilter ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul></nav>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ========================================================= -->
    <!-- CREATE / EDIT FORM                                         -->
    <!-- ========================================================= -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= $editStudent ? 'Edit Student' : 'Add Student' ?></h4>
        <a href="admin/students.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to list</a>
    </div>

    <?= show_flash() ?>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="save">

        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Personal Information</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?= e($editStudent['first_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?= e($editStudent['last_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">—</option>
                        <?php foreach (['male','female','other'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($editStudent['gender']??'')===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control"
                           value="<?= e($editStudent['dob'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select">
                        <option value="">— None —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= ($editStudent['class_id']??0)==$c['id']?'selected':'' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= e($editStudent['phone'] ?? '') ?>" placeholder="+231...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">County</label>
                    <select name="county" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($counties as $co): ?>
                            <option value="<?= e($co) ?>" <?= ($editStudent['county']??'')===$co?'selected':'' ?>><?= e($co) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <?php if (!empty($editStudent['photo'])): ?>
                        <img src="<?= UPLOAD_URL . e($editStudent['photo']) ?>" class="mt-2 rounded" style="height:50px;">
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= e($editStudent['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Name</label>
                    <input type="text" name="guardian_name" class="form-control"
                           value="<?= e($editStudent['guardian_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guardian Phone</label>
                    <input type="text" name="guardian_phone" class="form-control"
                           value="<?= e($editStudent['guardian_phone'] ?? '') ?>">
                </div>
            </div>
        </div></div></div>

        <div class="col-md-6"><div class="card h-100"><div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Login Account</h6>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= e($editStudent['username'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?= e($editStudent['email'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Password <?= $editStudent?'<small class="text-muted">(leave blank to keep current)</small>':'' ?></label>
                    <input type="password" name="password" class="form-control"
                           <?= $editStudent?'':'placeholder="Default: Student@123"' ?>>
                </div>
                <div class="col-12">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['active','inactive','suspended'] as $stt): ?>
                            <option value="<?= $stt ?>" <?= ($editStudent['status']??'active')===$stt?'selected':'' ?>><?= ucfirst($stt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div></div></div>

        <div class="col-12">
            <button class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> <?= $editStudent?'Update':'Create' ?> Student</button>
            <a href="admin/students.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    </div>
<?php endif; ?>

</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>