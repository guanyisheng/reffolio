<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/characters.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码。';
    } elseif (attempt_login($username, $password)) {
        flash('success', '登录成功。');
        redirect('/characters.php');
    } else {
        $error = '用户名或密码错误。';
    }
}

render_header('登录', ['body_class' => 'page-auth']);
?>
<div class="container auth-shell">
  <div class="auth-panel">
    <h1><?= e(site('site.login_title', '登录')) ?></h1>
    <p class="sub"><?= e(site('site.login_lead')) ?></p>
    <?php if ($error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="form-grid" autocomplete="on">
      <?= csrf_field() ?>
      <div class="form-row">
        <label for="username">用户名</label>
        <input id="username" name="username" type="text" required maxlength="64" value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label for="password">密码</label>
        <input id="password" name="password" type="password" required>
      </div>
      <button class="btn btn-primary" type="submit">登录</button>
    </form>
    <p class="foot">还没有账号？<a href="/register.php">注册</a></p>
  </div>
</div>
<?php render_footer(); ?>
