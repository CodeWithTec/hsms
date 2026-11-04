<?php
$pageTitle = 'News & Announcements';
$section   = 'Admin · News';
require_once __DIR__ . '/../includes/placeholder.php';




require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar_admin.php';

global $pdo;
$action = get('action','list');
$id = (int)get('id',0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fa = post('form_action');
    if ($fa === 'save') {
        $nid = (int)post('id');
        $title = post('title');
        $content = post('content');
        $category = post('category','General');
        $pinned = post('pinned') === '1' ? 1 : 0;
        if (!$title || !$content) {
            flash('error','Title and content required.');
        } else {
            if ($nid > 0) {
                $pdo->prepare("UPDATE news SET title=?, content=?, category=?, pinned=? WHERE id=?")
                    ->execute([$title,$content,$category,$pinned,$nid]);
                log_activity('update_news', "Updated news #$nid");
                flash_redirect('success','News updated.','admin/news.php');
            } else {
                $pdo->prepare("INSERT INTO news (title,content,category,pinned,author_id) VALUES (?,?,?,?,?)")
                    ->execute([$title,$content,$category,$pinned,user_id()]);
                log_activity('create_news', 'Posted: '.$title);
                flash_redirect('success','News posted.','admin/news.php');
            }
        }
        $action = $nid > 0 ? 'edit' : 'create';
    }
    if ($fa === 'delete') {
        $pdo->prepare("DELETE FROM news WHERE id=?")->execute([(int)post('id')]);
        flash_redirect('success','News deleted.','admin/news.php');
    }
}

$edit = null;
if ($action === 'edit' && $id > 0) {
    $s = $pdo->prepare("SELECT * FROM news WHERE id=? LIMIT 1");
    $s->execute([$id]);
    $edit = $s->fetch();
    if (!$edit) flash_redirect('error','Not found.','admin/news.php');
}

$news = $pdo->query("SELECT n.*, u.username FROM news n JOIN users u ON u.id=n.author_id
                     ORDER BY n.pinned DESC, n.created_at DESC")->fetchAll();
?>
<div class="p-4">

<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0">News &amp; Announcements</h4>
        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Post</a>
    </div>
    <?= show_flash() ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr>
                    <th>Title</th><th>Category</th><th>Author</th><th>Date</th><th>Pinned</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                    <?php if (empty($news)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No news yet.</td></tr>
                    <?php else: foreach ($news as $n): ?>
                        <tr>
                            <td><strong><?= e($n['title']) ?></strong>
                                <div class="small text-muted"><?= e(mb_strimwidth($n['content'],0,80,'…')) ?></div>
                            </td>
                            <td><span class="badge bg-secondary"><?= e($n['category']) ?></span></td>
                            <td><?= e($n['username']) ?></td>
                            <td><?= date('M j, Y', strtotime($n['created_at'])) ?></td>
                            <td><?= $n['pinned']?'<i class="bi bi-pin-angle-fill text-warning"></i>':'' ?></td>
                            <td class="text-end">
                                <a href="?action=edit&id=<?= (int)$n['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="d-flex justify-content-between mb-3">
        <h4 class="mb-0"><?= $edit?'Edit Post':'New Post' ?></h4>
        <a href="admin/news.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
    <?= show_flash() ?>
    <div class="card"><div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="id" value="<?= $edit?(int)$edit['id']:0 ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required value="<?= e($edit['title']??'') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= e($edit['category']??'General') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Content *</label>
                    <textarea name="content" class="form-control" rows="8" required><?= e($edit['content']??'') ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="pinned" value="1" id="pinned" class="form-check-input"
                               <?= !empty($edit['pinned'])?'checked':'' ?>>
                        <label for="pinned" class="form-check-label">Pin to top</label>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary px-4"><?= $edit?'Update':'Post' ?></button>
                    <a href="admin/news.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div></div>
<?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>