<?php
$pageTitle = 'Manage Teachers';
$section   = 'Admin · Teachers';
require_once __DIR__ . '/../includes/placeholder.php';

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$action = get('action', 'list');
$id = (int)get('id', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $formAction = post('form_action');

    if ($formAction === 'delete') {
        $delId = (int)post('id');
        $stmt = $pdo->prepare("SELECT u.id AS uid, t.staff_id FROM teachers t
                               JOIN users u ON u.id=t.user_id WHERE t.id=?");
        $stmt->execute([$delId]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$row['uid']]);
            log_activity('delete_teacher', 'Deleted ' . $row['staff_id']);
            flash_redirect('success', 'Teacher deleted.', 'admin/teachers.php');
        }
        flash_redirect('error', 'Teacher not found.', 'admin/teachers.php');
    }

    if ($formAction === 'save') {
        $errors = [];
        $fn = post('first_name'); $ln = post('last_name');
        $gender = post('gender'); $dob = post('dob') ?: null;
        $phone = post('phone'); $addr = post('address');
        $county = post('county'); $qual = post('qualification');
        $email = post('email'); $username = post('username');
        $password = post('password'); $status = post('status','active');

        if (!$fn) $errors[] = 'First name required.';
        if (!$ln) $errors[] = 'Last name required.';
        if (!$email) $errors[] = 'Email required.';
        if (!$username) $errors[] = 'Username required.';

        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $photo = upload_file('photo', 'teachers', ALLOWED_IMAGE_TYPES);
            if ($photo === null) $errors[] = 'Photo upload failed.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("SELECT user_id, photo FROM teachers WHERE id=?");
                    $stmt->execute([$id]);
                    $ex = $stmt->fetch();
                    if (!$ex) flash_redirect('error','Teacher not found.','admin/teachers.php');
                    $pdo->prepare("UPDATE users SET username=?, email=?, status=? WHERE id=?")
                        ->execute([$username, $email, $status, $ex['user_id']]);
                    if ($password !== '') {
                        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
                            ->execute([password_hash($password, PASSWORD_DEFAULT), $ex['user_id']]);
                    }
                    if ($photo === null) $photo = $ex['photo'];
                    $pdo->prepare("UPDATE teachers SET first_name=?, last_name=?, gender=?, dob=?,
                                   phone=?, address=?, county=?, qualification=?, photo=? WHERE id=?")
                        ->execute([$fn,$ln,$gender,$dob,$phone,$addr,$county,$qual,$photo,$id]);
                    log_activity('update_teacher', "Updated teacher #$id");
                    flash_redirect('success','Teacher updated.','admin/teachers.php');
                } else {
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO users (username,email,password_hash,role,status,must_change_password)
                                    VALUES (?,?,?,?,?,1)")
                        ->execute([$username,$email,
                                    password_hash($password ?: 'Teacher@123', PASSWORD_DEFAULT),
                                    'teacher',$status]);
                    $newUid = (int)$pdo->lastInsertId();
                    $staffId = next_teacher_id();
                    $pdo->prepare("INSERT INTO teachers
                                   (user_id, staff_id, first_name, last_name, gender, dob,
                                    phone, address, county, qualification, photo)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$newUid,$staffId,$fn,$ln,$gender,$dob,$phone,$addr,$county,$qual,$photo]);
                    $pdo->commit();
                    log_activity('create_teacher', "Created $staffId");
                    flash_redirect('success', "Teacher created. ID: $staffId | Default password: " . ($password ?: 'Teacher@123'), 'admin/teachers.php');
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('error', strpos($e->getMessage(),'Duplicate')!==false
                    ? 'Username or email already exists.'
                    : 'Database error: '.$e->getMessage());
            }
        } else {
            flash('error', implode(' ', $errors));
        }
        $action = $id > 0 ? 'edit' : 'create';
    }
}

$editTeacher = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.status
                           FROM teachers t JOIN users u ON u.id=t.user_id
                           WHERE t.id=? LIMIT 1");
    $stmt->execute([$id]);
    $editTeacher = $stmt->fetch();
    if (!$editTeacher) flash_redirect('error','Teacher not found.','admin/teachers.php');
}

if ($action === 'list') {
    $search = get('q');
    $page = max(1, (int)get('page', 1));
    $perPage = 15;
    $where = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.staff_id LIKE ?)";
        $params = ["%$search%","%$search%","%$search%"];
    }
    $total = $pdo->prepare("SELECT COUNT(*) FROM teachers t $where");
    $total->execute($params);
    $pg = paginate((int)$total->fetchColumn(), $perPage, $page);
    $sql = "SELECT t.* FROM teachers t $where ORDER BY t.created_at DESC
            LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
    $list = $pdo->prepare($sql);
    $list->execute($params);
    $teachers = $list->fetchAll();
}
?>
<div class="col-lg-10 p-4">

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Teachers</h4>
            <small class="text-muted"><?= $pg['total'] ?> total</small>
        </div>
        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Teacher</a>
    </div>

    <?= show_flash() ?>

    <div class="card mb-3"><div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search name or staff ID…">
            </div>
            <div class="col-md-4"><button class="btn btn-outline-primary w-100">Search</button></div>
        </form>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th>Photo</th><th>Staff ID</th><th>Name</th><th>Phone</th><th>County</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No teachers yet.</td></tr>
                    <?php else: foreach ($teachers as $t): ?>
                        <tr>
                            <td>
                                <?php if ($t['photo']): ?>
                                    <img src="<?= UPLOAD_URL . e($t['photo']) ?>" class="rounded-circle" style="width:38px;height:38px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:0.8rem;">
                                        <?= strtoupper(substr($t['first_name'],0,1) . substr($t['last_name'],0,1)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($t['staff_id']) ?></code></td>
                            <td><?= e($t['first_name'].' '.$t['last_name']) ?></td>
                            <td><?= e($t['phone'] ?? '—') ?></td>
                            <td><?= e($t['county'] ?? '—') ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this teacher?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pg['pages'] > 1): ?>
            <div class="card-footer"><nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
                    <li class="page-item <?= $i===$pg['page']?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav></div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= $editTeacher?'Edit Teacher':'Add Teacher' ?></h4>
        <a href="admin/teachers.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    <?= show_flash() ?>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="save">

        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Personal Information</h6>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= e($editTeacher['first_name']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= e($editTeacher['last_name']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">—</option>
                        <?php foreach (['male','female','other'] as $g): ?>
                            <option value="<?= $g ?>" <?= ($editTeacher['gender']??'')===$g?'selected':'' ?>><?= ucfirst($g) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label">DOB</label>
                    <input type="date" name="dob" class="form-control" value="<?= e($editTeacher['dob']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($editTeacher['phone']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">County</label>
                    <input type="text" name="county" class="form-control" value="<?= e($editTeacher['county']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($editTeacher['address']??'') ?></textarea></div>
                <div class="col-12"><label class="form-label">Qualification</label>
                    <input type="text" name="qualification" class="form-control" value="<?= e($editTeacher['qualification']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Photo</label>
                    <?php if (!empty($editTeacher['photo'])): ?>
                        <div class="mb-2">
                            <img src="<?= UPLOAD_URL . e($editTeacher['photo']) ?>" class="rounded-circle" style="width:60px;height:60px;object-fit:cover;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="photo" class="form-control"></div>
            </div>
        </div></div></div> 

        <div class="col-md-6"><div class="card"><div class="card-body">
            <h6 class="text-muted text-uppercase small mb-3">Account Information</h6>
            <div class="mb-3"><label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required value="<?= e($editTeacher['email']??'') ?>"></div>
            <div class="mb-3"><label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" required value="<?= e($editTeacher['username']??'') ?>"></div>
            <div class="mb-3"><label class="form-label">Password <?= $editTeacher?'(leave blank to keep current)':'(default: Teacher@123)' ?></label>
                <input type="password" name="password" class="form-control" autocomplete="new-password" <?= $editTeacher?'':'minlength="8"' ?>></div>
            <div class="mb-3"><label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['active','inactive'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= ($editTeacher['status']??'active')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> <?= $editTeacher?'Save Changes':'Create Teacher' ?></button>
                <a href="teachers.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div></div></div>
    </form>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
        
