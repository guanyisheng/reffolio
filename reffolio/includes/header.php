<?php
/** @var string $title */
/** @var string $bodyClass */
/** @var ?array $flash */
/** @var ?array $user */
$siteName = site('site.name', 'Reffolio');
$logoUrl = site_logo_url();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <script>
  (function () {
    try {
      var saved = localStorage.getItem("theme");
      var theme = saved === "dark" || saved === "light"
        ? saved
        : (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
      document.documentElement.setAttribute("data-theme", theme);
    } catch (e) {}
  })();
  </script>
  <title><?= e(site_page_title($title)) ?></title>
  <?php if ($logoUrl): ?>
    <link rel="icon" href="<?= e($logoUrl) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset_ver('assets/css/style.css')) ?>">
  <meta http-equiv="Cache-Control" content="no-cache">
</head>
<body class="<?= e($bodyClass) ?>">
  <div class="page-bg" aria-hidden="true"></div>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="/index.php">
        <?php if ($logoUrl): ?>
          <img class="brand-logo" src="<?= e($logoUrl) ?>" alt="">
        <?php else: ?>
          <span class="brand-mark" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="brand-text"><?= e($siteName) ?></span>
      </a>
      <nav class="nav" id="site-nav">
        <?php if ($user): ?>
          <a href="/characters.php"><?= e(site('site.nav.characters', '我的角色')) ?></a>
          <a href="/works.php"><?= e(site('site.nav.works', '全部稿件')) ?></a>
          <a href="/categories.php"><?= e(site('site.nav.categories', '稿件分类')) ?></a>
          <a href="/create_character.php"><?= e(site('site.nav.create_char', '新建角色')) ?></a>
          <a href="/upload_work.php"><?= e(site('site.nav.upload', '上传稿件')) ?></a>
          <a href="/invites.php"><?= e(site('site.nav.invites', '画师链接')) ?></a>
          <?php if (is_admin($user)): ?>
            <a href="/settings.php"><?= e(site('site.nav.storage', '存储设置')) ?></a>
            <a href="/site_settings.php"><?= e(site('site.nav.site', '站点设置')) ?></a>
            <a href="/users.php"><?= e(site('site.nav.users', '用户管理')) ?></a>
          <?php endif; ?>
          <span class="nav-user">
            <?= e($user['display_name'] ?: $user['username']) ?>
            <?php if (is_admin($user)): ?>
              <span class="admin-badge" title="管理员">1</span>
            <?php endif; ?>
          </span>
          <a class="btn btn-ghost btn-sm" href="/logout.php">退出</a>
        <?php else: ?>
          <a href="/login.php">登录</a>
          <a class="btn btn-primary btn-sm" href="/register.php">注册</a>
        <?php endif; ?>
      </nav>
      <div class="header-actions">
        <button type="button" class="theme-toggle" id="theme-toggle" aria-label="切换主题">
          <span class="theme-icon theme-icon-sun" aria-hidden="true">☀</span>
          <span class="theme-icon theme-icon-moon" aria-hidden="true">☾</span>
        </button>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="site-nav" aria-label="打开菜单">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <?php if ($flash): ?>
    <div class="container">
      <div class="flash flash-<?= e($flash['type']) ?> reveal" role="alert">
        <?= e($flash['message']) ?>
      </div>
    </div>
  <?php endif; ?>

  <main class="site-main">
