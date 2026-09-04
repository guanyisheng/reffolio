<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'create');
    try {
        if ($action === 'delete') {
            delete_work_category((int) $user['id'], (int) ($_POST['category_id'] ?? 0));
            flash('success', '分类已删除，相关稿件变为「未分类」。');
            redirect('/categories.php');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        create_work_category((int) $user['id'], $name);
        flash('success', '分类「' . $name . '」已创建。');
        redirect('/categories.php');
    } catch (Throwable $e) {
        $error = user_error_message($e);
    }
}

$categories = get_user_categories((int) $user['id']);

render_header('稿件分类');
?>
<div class="container">
  <h1 class="page-title">稿件分类</h1>
  <p class="page-lead">为稿件建立分类，便于在角色页和列表中筛选。例如：立绘、Q 版、表情包、服设、贺图。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="wizard-panel active" style="display:block;max-width:520px;margin-bottom:2rem;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-grid">
      <div class="form-row">
        <label for="name">新建分类</label>
        <input id="name" name="name" type="text" required maxlength="64" placeholder="例如：Q版 / 立绘 / 服设">
      </div>
    </div>
    <div class="btn-row">
      <button class="btn btn-primary" type="submit">添加分类</button>
      <a class="btn btn-ghost" href="/works.php">返回稿件</a>
    </div>
  </form>

  <h2 class="section-title">已有分类 <span><?= count($categories) ?></span></h2>
  <?php if (!$categories): ?>
    <div class="empty-state"><p>还没有分类，上传稿件时可选择「未分类」。</p></div>
  <?php else: ?>
    <div class="work-list">
      <?php foreach ($categories as $cat): ?>
        <div class="work-item" style="grid-template-columns:1fr auto;cursor:default;">
          <div class="work-info">
            <h3><?= e(row_str($cat, 'name')) ?></h3>
            <p><?= (int) ($cat['work_count'] ?? 0) ?> 件稿件</p>
          </div>
          <form method="post" onsubmit="return confirm('删除后，该分类下的稿件将变为「未分类」，确定？');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
            <button class="btn btn-ghost btn-sm" type="submit">删除</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
