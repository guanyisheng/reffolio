<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_admin();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'grant') {
            set_user_admin((int) ($_POST['user_id'] ?? 0), true);
            flash('success', '已设为管理员。');
        } elseif ($action === 'revoke') {
            set_user_admin((int) ($_POST['user_id'] ?? 0), false);
            flash('success', '已取消管理员。');
        } else {
            throw new InvalidArgumentException('无效操作。');
        }
        redirect('/users.php');
    } catch (Throwable $e) {
        $error = user_error_message($e);
    }
}

$users = list_all_users();

render_header('用户管理');
?>
<div class="container">
  <h1 class="page-title">用户管理</h1>
  <p class="page-lead">管理员可指定其他用户为管理员。管理员才能修改站点设置与存储配置。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <h2 class="section-title">全部用户 <span><?= count($users) ?></span></h2>
  <?php if (!$users): ?>
    <div class="empty-state"><p>暂无用户。</p></div>
  <?php else: ?>
    <div class="work-list">
      <?php foreach ($users as $row): ?>
        <?php
          $uid = (int) $row['id'];
          $rowAdmin = is_admin($row);
          $isSelf = $uid === (int) $user['id'];
        ?>
        <div class="work-item user-row" style="grid-template-columns:auto 1fr auto;cursor:default;">
          <div class="user-id-cell">
            <span class="user-id"><?= $uid ?></span>
            <?php if ($rowAdmin): ?>
              <span class="admin-badge" title="管理员">1</span>
            <?php endif; ?>
          </div>
          <div class="work-info">
            <h3><?= e($row['display_name'] ?: $row['username']) ?></h3>
            <p>
              @<?= e($row['username']) ?>
              <?php if ($isSelf): ?><span style="color:var(--ink-muted);"> · 当前账号</span><?php endif; ?>
            </p>
          </div>
          <div>
            <?php if ($rowAdmin): ?>
              <?php if ($isSelf): ?>
                <span class="hint">本人</span>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('确定取消该用户的管理员权限？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="revoke">
                  <input type="hidden" name="user_id" value="<?= $uid ?>">
                  <button class="btn btn-ghost btn-sm" type="submit">取消管理员</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="grant">
                <input type="hidden" name="user_id" value="<?= $uid ?>">
                <button class="btn btn-primary btn-sm" type="submit">设为管理员</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="btn-row" style="margin-top:1.5rem;">
    <a class="btn btn-ghost" href="/characters.php">返回</a>
  </div>
</div>
<?php render_footer(); ?>
