<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/characters.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = register_user(
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            trim((string) ($_POST['display_name'] ?? '')) ?: null
        );
        login_user($id);
        flash('success', '注册成功，欢迎使用。');
        redirect('/characters.php');
    } catch (InvalidArgumentException $e) {
        $error = user_error_message($e);
    } catch (Throwable $e) {
        $error = user_error_message($e);
    }
}

render_header('注册', ['body_class' => 'page-auth']);
?>
<div class="container auth-shell">
  <div class="auth-panel">
    <h1><?= e(site('site.register_title', '注册')) ?></h1>
    <p class="sub"><?= e(site('site.register_lead')) ?></p>
    <?php if ($error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="username">用户名</label>
        <input id="username" name="username" type="text" required maxlength="64" value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label for="display_name">显示名称（可选）</label>
        <input id="display_name" name="display_name" type="text" maxlength="64" value="<?= e($_POST['display_name'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label for="password">密码</label>
        <input id="password" name="password" type="password" required minlength="6">
        <span class="hint">至少 6 位</span>
      </div>
      <button class="btn btn-primary" type="submit">创建账号</button>
    </form>
    <p class="foot">已有账号？<a href="/login.php">登录</a></p>
  </div>
</div>
<?php render_footer(); ?>
