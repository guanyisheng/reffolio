<?php
/**
 * 统一存储层：local / 腾讯云 COS
 *
 * 数据库中路径约定：
 *   - 本地：uploads/character/1/xxx.jpg
 *   - COS ：cos://your-bucket/character/1/xxx.jpg  （cos:// + objectKey）
 */
declare(strict_types=1);

require_once ROOT_PATH . '/includes/TencentCosClient.php';
require_once ROOT_PATH . '/includes/settings.php';

function storage_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $base = require ROOT_PATH . '/config/storage.php';
        $cfg = merge_storage_settings($base);
    }
    return $cfg;
}

/** 清除静态缓存（保存设置后重新读取） */
function storage_config_reset(): void
{
    // 通过重新请求页面即可；此处供同请求内测试使用
}

function storage_driver(): string
{
    $driver = storage_config()['driver'] ?? 'local';
    return $driver === 'cos' ? 'cos' : 'local';
}

function cos_client(): TencentCosClient
{
    static $client = null;
    if ($client === null) {
        $client = new TencentCosClient(storage_config()['cos'] ?? []);
    }
    return $client;
}

function is_cos_path(?string $path): bool
{
    return is_string($path) && str_starts_with($path, 'cos://');
}

function cos_object_key_from_path(string $path): string
{
    return ltrim(substr($path, strlen('cos://')), '/');
}

/**
 * 校验并保存上传图片，按当前驱动写入本地或 COS。
 * 返回写入数据库的路径字符串。
 */
function save_uploaded_image(array $file, string $subdir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('文件上传失败，错误码：' . ($file['error'] ?? -1));
    }

    if (($file['size'] ?? 0) <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('文件大小不符合要求（单张最大 ' . upload_max_bytes_human() . '）。');
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('非法上传文件。');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    if ($mime === false || !isset(ALLOWED_MIME[$mime])) {
        throw new RuntimeException('仅允许上传 JPG / PNG / WEBP / GIF 图片。');
    }

    $ext = ALLOWED_MIME[$mime];
    $originalName = (string) ($file['name'] ?? '');
    $origExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $blockedExt = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'js', 'html', 'htm', 'shtml'];
    if (in_array($origExt, $blockedExt, true)) {
        throw new RuntimeException('禁止上传可执行脚本文件。');
    }

    $imageInfo = @getimagesize($tmp);
    if ($imageInfo === false) {
        throw new RuntimeException('文件不是有效的图片。');
    }

    $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $relative = trim($subdir, '/') . '/' . $filename;

    if (storage_driver() === 'cos') {
        $client = cos_client();
        $objectKey = $client->objectKey($relative);
        $client->putObject($tmp, $objectKey, $mime);
        @unlink($tmp);
        return 'cos://' . $objectKey;
    }

    // 本地存储
    $baseDir = storage_config()['local']['base_dir'] ?? 'uploads';
    $dir = ROOT_PATH . '/' . trim($baseDir, '/') . '/' . trim($subdir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('无法创建上传目录。');
    }

    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('保存文件失败。');
    }
    @chmod($dest, 0644);

    return trim($baseDir, '/') . '/' . $relative;
}

function delete_upload_file(?string $path): void
{
    if ($path === null || $path === '') {
        return;
    }

    if (is_cos_path($path)) {
        try {
            cos_client()->deleteObject(cos_object_key_from_path($path));
        } catch (Throwable $e) {
            // 删除失败不阻断主流程
        }
        return;
    }

    $full = ROOT_PATH . '/' . ltrim($path, '/');
    $realRoot = realpath(ROOT_PATH . '/uploads');
    $realFile = realpath($full);
    if ($realRoot && $realFile && str_starts_with($realFile, $realRoot) && is_file($realFile)) {
        @unlink($realFile);
    }
}

/**
 * 生成可访问的图片 URL（COS 私有桶返回临时签名链接）
 */
function image_url(?string $path, string $fallback = '/assets/img/placeholder.svg'): string
{
    if ($path === null || $path === '') {
        return $fallback;
    }

    if (is_cos_path($path)) {
        try {
            return cos_client()->getSignedUrl(cos_object_key_from_path($path));
        } catch (Throwable $e) {
            return $fallback;
        }
    }

    // 兼容历史本地路径
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return '/' . ltrim($path, '/');
}

/**
 * 将存储路径解析为可打包的本地临时文件。
 * 返回 [localPath, shouldUnlink]
 */
function resolve_storage_to_local(string $path): ?array
{
    if (is_cos_path($path)) {
        try {
            $tmp = cos_client()->downloadToTemp(cos_object_key_from_path($path));
            return [$tmp, true];
        } catch (Throwable $e) {
            return null;
        }
    }

    $full = ROOT_PATH . '/' . ltrim($path, '/');
    $realRoot = realpath(ROOT_PATH . '/uploads');
    $realFile = realpath($full);
    if ($realRoot && $realFile && str_starts_with($realFile, $realRoot) && is_file($realFile)) {
        return [$realFile, false];
    }
    return null;
}
