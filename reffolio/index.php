<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
if ($user) {
    redirect('/characters.php');
}

render_header(site('site.home_title', '角色设定与稿件'), ['body_class' => 'page-home']);
?>
<div class="container">
  <section class="home-hero">
    <h1><?= e(site('site.home_title', '角色设定与稿件档案')) ?></h1>
    <p><?= e(site('site.home_lead')) ?></p>
    <div class="btn-row">
      <a class="btn btn-primary" href="/register.php">开始使用</a>
      <a class="btn btn-ghost" href="/login.php">已有账号登录</a>
    </div>
  </section>
</div>
<?php render_footer(); ?>
