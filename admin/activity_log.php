<?php
$pageTitle = 'Activity Log';
$section   = 'Admin · Audit';
require_once __DIR__ . '/../includes/placeholder.php';



require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;

$actionFilter = get('action','');
$page = max(1,(int)get('page',1));
$perPage = 30;

$where = "WHERE 1=1"; $params = [];
if ($actionFilter !== '') {
    $where .= " AND l.action=?";
    $params[] = $actionFilter;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM activity_log l $where");
$total->execute($params);
$pg = paginate((int)$total->fetchColumn(), $perPage, $page);

$sql = "SELECT l.*, u.username FROM activity_log l
        LEFT JOIN users u ON u.id=l.user_id
        $where ORDER BY l.created_at DESC
        LIMIT {$pg['per_page']} OFFSET {$pg['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="col-lg-10 p-4">
    <h4 class="mb-3">Activity Log</h4>

    <form method="get" class="card mb-3"><div class="card-body row g-2">
        <div class="col-md-6">
            <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="">All actions</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a) ?>" <?= $actionFilter===$a?'selected':'' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <a href="admin/activity_log.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </div></form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No activity.</td></tr>
                    <?php else: foreach ($logs as $l): ?>
                        <tr>
                            <td class="small text-muted text-nowrap"><?= date('M j, g:i A', strtotime($l['created_at'])) ?></td>
                            <td><?= e($l['username'] ?? '—') ?></td>
                            <td><span class="badge bg-secondary"><?= e($l['role'] ?? '—') ?></span></td>
                            <td><span class="badge bg-primary"><?= e($l['action']) ?></span></td>
                            <td class="small"><?= e($l['details']) ?></td>
                            <td class="small text-muted"><?= e($l['ip_address'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pg['pages'] > 1): ?>
            <div class="card-footer"><nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
                    <li class="page-item <?= $i===$pg['page']?'active':'' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&action=<?= urlencode($actionFilter) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>