<?php
/**
 * 公共工具函数
 */
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function upload_max_bytes_human(): string
{
    $bytes = defined('UPLOAD_MAX_BYTES') ? UPLOAD_MAX_BYTES : 50 * 1024 * 1024;
    if ($bytes >= 1024 * 1024) {
        return (int) round($bytes / (1024 * 1024)) . 'MB';
    }
    return (int) round($bytes / 1024) . 'KB';
}

/** 面向用户展示的完整错误信息（含底层原因） */
function user_error_message(Throwable $e): string
{
    $msg = trim($e->getMessage());
    if ($msg === '') {
        $msg = $e::class;
    }

    $prev = $e->getPrevious();
    if ($prev instanceof Throwable) {
        $prevMsg = trim($prev->getMessage());
        if ($prevMsg !== '' && !str_contains($msg, $prevMsg)) {
            $msg .= '（原因：' . $prevMsg . '）';
        }
    }

    return $msg;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('无效的请求令牌。');
    }
}

function parse_tags(?string $tags): array
{
    if ($tags === null || trim($tags) === '') {
        return [];
    }
    $parts = preg_split('/[,，\s]+/u', $tags) ?: [];
    $clean = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $clean[] = $part;
        }
    }
    return array_values(array_unique($clean));
}

function tags_to_string(array $tags): string
{
    return implode(',', $tags);
}

function format_date(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '未填写';
    }
    return $date;
}

function asset_url(string $path): string
{
    return '/' . ltrim($path, '/');
}

/**
 * 静态资源 URL（带文件修改时间，避免 CDN/浏览器长期缓存旧 CSS/JS）
 */
function asset_ver(string $path): string
{
    $rel = ltrim($path, '/');
    $full = ROOT_PATH . '/' . $rel;
    $ver = is_file($full) ? (string) filemtime($full) : '1';
    return '/' . $rel . '?v=' . rawurlencode($ver);
}

/**
 * 动态页面禁止 CDN/浏览器缓存
 */
function send_no_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('CDN-Cache-Control: no-store');
    header('X-Accel-Expires: 0');
}

/**
 * 从 PDO 行中安全读取字段（兼容不同大小写键名）
 */
function row_field(array $row, string $field, mixed $default = ''): mixed
{
    if (array_key_exists($field, $row)) {
        return $row[$field];
    }
    $lower = strtolower($field);
    foreach ($row as $key => $value) {
        if (is_string($key) && strtolower($key) === $lower) {
            return $value;
        }
    }
    return $default;
}

function row_str(array $row, string $field, string $default = ''): string
{
    $val = row_field($row, $field, $default);
    if ($val === null) {
        return $default;
    }
    return trim((string) $val);
}

function display_character_name(array $row, string $fallback = '未命名角色'): string
{
    $name = row_str($row, 'name');
    return $name !== '' ? $name : $fallback;
}

/** 角色头像路径：优先 avatar_image，否则回退 cover_image */
function character_avatar_path(array $character): ?string
{
    $avatar = row_str($character, 'avatar_image');
    if ($avatar !== '') {
        return $avatar;
    }
    $cover = row_str($character, 'cover_image');
    return $cover !== '' ? $cover : null;
}

function character_uses_image_as_avatar(array $character, string $imagePath): bool
{
    if ($imagePath === '') {
        return false;
    }
    $avatar = row_str($character, 'avatar_image');
    if ($avatar !== '') {
        return $avatar === $imagePath;
    }
    return row_str($character, 'cover_image') === $imagePath;
}

function display_work_title(array $row, string $fallback = '未命名稿件'): string
{
    $title = row_str($row, 'title');
    return $title !== '' ? $title : $fallback;
}

function render_page_back(string $href, string $label = '返回'): void
{
    echo '<a class="page-back" href="' . e($href) . '"><span class="page-back-icon" aria-hidden="true">←</span> '
        . e($label) . '</a>';
}

function render_header(?string $title = '', array $options = []): void
{
    $title = trim((string) $title);
    if ($title === '') {
        $title = site('site.title_suffix', '角色设定档案');
    }
    $flash = take_flash();
    $user  = current_user();
    $bodyClass = $options['body_class'] ?? '';
    include ROOT_PATH . '/includes/header.php';
}

function render_footer(): void
{
    include ROOT_PATH . '/includes/footer.php';
}

/**
 * 将 PHP 多文件上传数组规范化为 [0=>[name,type,tmp_name,error,size], ...]
 * 兼容 images[] 多选，以及单文件 name 为字符串的情况。
 */
function reindex_files(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    // 单文件：name 为字符串
    if (!is_array($files['name'])) {
        return [[
            'name'     => (string) ($files['name'] ?? ''),
            'type'     => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error'    => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($files['size'] ?? 0),
        ]];
    }

    $out = [];
    foreach ($files['name'] as $i => $name) {
        $out[$i] = [
            'name'     => (string) ($name ?? ''),
            'type'     => (string) ($files['type'][$i] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
            'error'    => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size'     => (int) ($files['size'][$i] ?? 0),
        ];
    }
    return $out;
}

function absolute_url(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
