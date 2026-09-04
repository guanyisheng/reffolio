<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_admin();
$error = '';
$cfg = reload_site_config();
$post = [];

$fieldMap = [
    'site_name'           => 'site.name',
    'site_title_suffix'   => 'site.title_suffix',
    'site_home_title'     => 'site.home_title',
    'site_home_lead'      => 'site.home_lead',
    'site_footer'         => 'site.footer',
    'site_login_title'    => 'site.login_title',
    'site_login_lead'     => 'site.login_lead',
    'site_register_title' => 'site.register_title',
    'site_register_lead'  => 'site.register_lead',
    'site_share_char'     => 'site.share_char_badge',
    'site_share_work'     => 'site.share_work_badge',
    'nav_characters'      => 'site.nav.characters',
    'nav_works'           => 'site.nav.works',
    'nav_categories'      => 'site.nav.categories',
    'nav_create_char'     => 'site.nav.create_char',
    'nav_upload'          => 'site.nav.upload',
    'nav_invites'         => 'site.nav.invites',
    'nav_storage'         => 'site.nav.storage',
    'nav_site'            => 'site.nav.site',
    'nav_users'           => 'site.nav.users',
];

function site_form_val(array $cfg, array $post, string $postKey, string $cfgKey): string
{
    if (array_key_exists($postKey, $post)) {
        return trim((string) $post[$postKey]);
    }
    return (string) ($cfg[$cfgKey] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '提交内容过大，请减小 Logo 图片后重试。';
    } else {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'save');

        try {
            if ($action === 'delete_logo') {
                delete_site_logo();
                flash('success', 'Logo 已移除。');
                redirect('/site_settings.php');
            }
            if ($action === 'reset') {
                reset_site_settings();
                flash('success', '已恢复默认站点设置。');
                redirect('/site_settings.php');
            }

            save_site_settings_from_post($_POST);
            $logoWarning = '';

            if (!empty($_POST['cos_logo_token'])) {
                try {
                    $logoPath = consume_cos_logo_token((int) $user['id']);
                    $old = trim(get_setting('site.logo', '') ?? '');
                    if ($old !== '' && $old !== $logoPath) {
                        delete_upload_file($old);
                    }
                    set_setting('site.logo', $logoPath);
                    reload_site_config();
                } catch (Throwable $e) {
                    $logoWarning = '文案已保存，但 Logo 直传失败：' . user_error_message($e);
                }
            } elseif (!empty($_FILES['site_logo']['name'])) {
                try {
                    $logoPath = save_site_logo_upload($_FILES['site_logo']);
                    $old = trim(get_setting('site.logo', '') ?? '');
                    if ($old !== '' && $old !== $logoPath) {
                        delete_upload_file($old);
                    }
                    set_setting('site.logo', $logoPath);
                    reload_site_config();
                } catch (Throwable $e) {
                    $logoWarning = '文案已保存，但 Logo 上传失败：' . user_error_message($e);
                }
            }

            if ($logoWarning !== '') {
                flash('success', $logoWarning);
            } else {
                flash('success', '站点设置已保存。');
            }
            redirect('/site_settings.php');
        } catch (Throwable $e) {
            $error = user_error_message($e);
            $post = $_POST;
            $cfg = reload_site_config();
        }
    }
}

$logoUrl = site_logo_url();
$v = static fn(string $postKey, string $cfgKey): string => site_form_val($cfg, $post, $postKey, $cfgKey);

render_header('站点设置');
?>
<div class="container settings-page">
  <div class="settings-head">
    <div>
      <h1 class="page-title">站点设置</h1>
      <p class="page-lead">自定义全站 Logo、标题、首页文案、导航文字与页脚。保存后立即生效。</p>
    </div>
    <a class="btn btn-ghost" href="/characters.php">返回</a>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="settings-form" data-upload-progress data-cos-context="site_logo">
    <?= csrf_field() ?>

    <div class="settings-layout">
      <aside class="settings-nav" aria-label="设置分区">
        <a href="#sec-brand">品牌与 Logo</a>
        <a href="#sec-home">首页文案</a>
        <a href="#sec-auth">登录 / 注册</a>
        <a href="#sec-share">分享页</a>
        <a href="#sec-nav">导航菜单</a>
      </aside>

      <div class="settings-panels">
        <section class="settings-card" id="sec-brand">
          <header class="settings-card-head">
            <h2>品牌与 Logo</h2>
            <p>顶部导航显示的名称与图标</p>
          </header>
          <div class="settings-card-body">
            <div class="logo-upload-row">
              <div class="logo-preview-box">
                <?php if ($logoUrl): ?>
                  <img src="<?= e($logoUrl) ?>" alt="当前 Logo" class="logo-preview-img">
                <?php else: ?>
                  <div class="logo-preview-placeholder">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <span><?= e($v('site_name', 'site.name')) ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="logo-upload-fields">
                <div class="form-row">
                  <label for="site_name">站点名称</label>
                  <input id="site_name" name="site_name" type="text" maxlength="64" value="<?= e($v('site_name', 'site.name')) ?>" placeholder="Reffolio">
                </div>
                <div class="form-row">
                  <label for="site_logo">上传 Logo</label>
                  <input id="site_logo" name="site_logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                  <span class="hint">PNG / JPG / WEBP / GIF，建议高度 32–48px。不上传则保留当前 Logo。</span>
                </div>
                <?php if ($logoUrl): ?>
                  <button class="btn btn-ghost btn-sm logo-remove-btn" type="submit" name="action" value="delete_logo" onclick="return confirm('确定移除 Logo？');">移除 Logo</button>
                <?php endif; ?>
              </div>
            </div>
            <div class="form-grid form-grid-2">
              <div class="form-row">
                <label for="site_title_suffix">浏览器标题后缀</label>
                <input id="site_title_suffix" name="site_title_suffix" type="text" maxlength="64" value="<?= e($v('site_title_suffix', 'site.title_suffix')) ?>" placeholder="角色设定档案">
                <span class="hint">例如：页面名 · <?= e($v('site_title_suffix', 'site.title_suffix')) ?></span>
              </div>
              <div class="form-row">
                <label for="site_footer">页脚文字</label>
                <input id="site_footer" name="site_footer" type="text" maxlength="255" value="<?= e($v('site_footer', 'site.footer')) ?>">
              </div>
            </div>
          </div>
        </section>

        <section class="settings-card" id="sec-home">
          <header class="settings-card-head">
            <h2>首页文案</h2>
            <p>未登录用户访问首页时看到的内容</p>
          </header>
          <div class="settings-card-body form-grid">
            <div class="form-row">
              <label for="site_home_title">首页大标题</label>
              <input id="site_home_title" name="site_home_title" type="text" maxlength="128" value="<?= e($v('site_home_title', 'site.home_title')) ?>">
            </div>
            <div class="form-row">
              <label for="site_home_lead">首页简介</label>
              <textarea id="site_home_lead" name="site_home_lead" rows="4"><?= e($v('site_home_lead', 'site.home_lead')) ?></textarea>
            </div>
          </div>
        </section>

        <section class="settings-card" id="sec-auth">
          <header class="settings-card-head">
            <h2>登录 / 注册</h2>
            <p>认证页面的标题与说明</p>
          </header>
          <div class="settings-card-body form-grid form-grid-2">
            <div class="form-row">
              <label for="site_login_title">登录页标题</label>
              <input id="site_login_title" name="site_login_title" type="text" maxlength="64" value="<?= e($v('site_login_title', 'site.login_title')) ?>">
            </div>
            <div class="form-row">
              <label for="site_login_lead">登录页说明</label>
              <input id="site_login_lead" name="site_login_lead" type="text" maxlength="255" value="<?= e($v('site_login_lead', 'site.login_lead')) ?>">
            </div>
            <div class="form-row">
              <label for="site_register_title">注册页标题</label>
              <input id="site_register_title" name="site_register_title" type="text" maxlength="64" value="<?= e($v('site_register_title', 'site.register_title')) ?>">
            </div>
            <div class="form-row">
              <label for="site_register_lead">注册页说明</label>
              <input id="site_register_lead" name="site_register_lead" type="text" maxlength="255" value="<?= e($v('site_register_lead', 'site.register_lead')) ?>">
            </div>
          </div>
        </section>

        <section class="settings-card" id="sec-share">
          <header class="settings-card-head">
            <h2>分享页标签</h2>
            <p>公开分享角色 / 稿件时顶部的标签文字</p>
          </header>
          <div class="settings-card-body form-grid form-grid-2">
            <div class="form-row">
              <label for="site_share_char">角色分享标签</label>
              <input id="site_share_char" name="site_share_char" type="text" maxlength="64" value="<?= e($v('site_share_char', 'site.share_char_badge')) ?>">
            </div>
            <div class="form-row">
              <label for="site_share_work">稿件分享标签</label>
              <input id="site_share_work" name="site_share_work" type="text" maxlength="64" value="<?= e($v('site_share_work', 'site.share_work_badge')) ?>">
            </div>
          </div>
        </section>

        <section class="settings-card" id="sec-nav">
          <header class="settings-card-head">
            <h2>导航菜单文字</h2>
            <p>顶部导航各菜单项的显示名称</p>
          </header>
          <div class="settings-card-body form-grid form-grid-2">
            <div class="form-row">
              <label for="nav_characters">我的角色</label>
              <input id="nav_characters" name="nav_characters" type="text" maxlength="32" value="<?= e($v('nav_characters', 'site.nav.characters')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_works">全部稿件</label>
              <input id="nav_works" name="nav_works" type="text" maxlength="32" value="<?= e($v('nav_works', 'site.nav.works')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_categories">稿件分类</label>
              <input id="nav_categories" name="nav_categories" type="text" maxlength="32" value="<?= e($v('nav_categories', 'site.nav.categories')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_create_char">新建角色</label>
              <input id="nav_create_char" name="nav_create_char" type="text" maxlength="32" value="<?= e($v('nav_create_char', 'site.nav.create_char')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_upload">上传稿件</label>
              <input id="nav_upload" name="nav_upload" type="text" maxlength="32" value="<?= e($v('nav_upload', 'site.nav.upload')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_invites">画师链接</label>
              <input id="nav_invites" name="nav_invites" type="text" maxlength="32" value="<?= e($v('nav_invites', 'site.nav.invites')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_storage">存储设置</label>
              <input id="nav_storage" name="nav_storage" type="text" maxlength="32" value="<?= e($v('nav_storage', 'site.nav.storage')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_site">站点设置</label>
              <input id="nav_site" name="nav_site" type="text" maxlength="32" value="<?= e($v('nav_site', 'site.nav.site')) ?>">
            </div>
            <div class="form-row">
              <label for="nav_users">用户管理</label>
              <input id="nav_users" name="nav_users" type="text" maxlength="32" value="<?= e($v('nav_users', 'site.nav.users')) ?>">
            </div>
          </div>
        </section>
      </div>
    </div>

    <div class="settings-actions">
      <button class="btn btn-primary" type="submit" name="action" value="save">保存全部设置</button>
      <button class="btn btn-ghost" type="submit" name="action" value="reset" onclick="return confirm('确定恢复全部默认文案？Logo 也会被移除。');">恢复默认</button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
