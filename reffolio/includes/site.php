<?php
/**
 * 站点外观与文案设置（全站）
 */
declare(strict_types=1);

function site_setting_defaults(): array
{
    return [
        'site.name'              => 'Reffolio',
        'site.logo'              => '',
        'site.title_suffix'      => '角色设定档案',
        'site.home_title'        => '角色设定与稿件档案',
        'site.home_lead'         => '为画师与兽设用户打造的资料卡系统——一个角色对应全部主设与绘画稿件，干净展示，方便分享。',
        'site.footer'            => '角色设定与稿件管理系统 · Reffolio',
        'site.login_title'       => '登录',
        'site.login_lead'        => '管理你的角色设定与绘画稿件',
        'site.register_title'    => '注册',
        'site.register_lead'     => '创建账号，开始整理角色与稿件',
        'site.share_char_badge'  => '公开分享 · 角色设定',
        'site.share_work_badge'  => '公开分享 · 单个稿件',
        'site.nav.characters'    => '我的角色',
        'site.nav.works'         => '全部稿件',
        'site.nav.categories'    => '稿件分类',
        'site.nav.create_char'   => '新建角色',
        'site.nav.upload'        => '上传稿件',
        'site.nav.invites'       => '画师链接',
        'site.nav.storage'       => '存储设置',
        'site.nav.site'          => '站点设置',
        'site.nav.users'         => '用户管理',
    ];
}

function site_config(bool $fresh = false): array
{
    static $cached = null;
    if ($fresh) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    $defaults = site_setting_defaults();
    $keys = array_keys($defaults);
    $map = get_settings_map($keys);
    foreach ($defaults as $key => $default) {
        if (array_key_exists($key, $map) && $map[$key] !== null) {
            $defaults[$key] = $map[$key];
        }
    }
    $cached = $defaults;
    return $cached;
}

function reload_site_config(): array
{
    return site_config(true);
}

function site(string $key, string $default = ''): string
{
    $cfg = site_config();
    return (string) ($cfg[$key] ?? $default);
}

function site_page_title(string $pageTitle): string
{
    $suffix = trim(site('site.title_suffix'));
    if ($pageTitle === '' || $pageTitle === '角色设定档案') {
        return $suffix !== '' ? $suffix : '角色设定档案';
    }
    return $suffix !== '' ? ($pageTitle . ' · ' . $suffix) : $pageTitle;
}

function site_logo_url(): ?string
{
    $logo = trim(site('site.logo'));
    if ($logo === '') {
        return null;
    }
    return image_url($logo, '');
}

function save_site_settings_from_post(array $post): void
{
    $fields = [
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
    try {
        foreach ($fields as $postKey => $settingKey) {
            set_setting($settingKey, trim((string) ($post[$postKey] ?? '')));
        }
    } catch (Throwable $e) {
        throw new RuntimeException(user_error_message($e), 0, $e);
    }
    reload_site_config();
}

function save_site_logo_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('请选择 Logo 图片。');
    }
    $path = save_uploaded_image($file, 'site');
    return $path;
}

function delete_site_logo(): void
{
    $current = trim(get_setting('site.logo', '') ?? '');
    if ($current !== '') {
        delete_upload_file($current);
    }
    set_setting('site.logo', '');
    reload_site_config();
}

function reset_site_settings(): void
{
    global $pdo;
    delete_site_logo();
    $keys = array_keys(site_setting_defaults());
    $keys = array_values(array_filter($keys, static fn(string $k): bool => $k !== 'site.logo'));
    if (!$keys) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("DELETE FROM app_settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    reload_site_config();
}
