<?php
/**
 * 腾讯云 COS 浏览器直传：预签名、会话凭证、消费
 */
declare(strict_types=1);

function cos_upload_session_key(): string
{
    return 'cos_pending';
}

function cos_allowed_mime(string $mime): ?string
{
    return ALLOWED_MIME[$mime] ?? null;
}

function cos_validate_file_meta(string $name, int $size, string $mime): string
{
    if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
        throw new InvalidArgumentException('文件大小不符合要求（单张最大 ' . upload_max_bytes_human() . '）。');
    }
    $ext = cos_allowed_mime($mime);
    if ($ext === null) {
        throw new InvalidArgumentException('仅允许上传 JPG / PNG / WEBP / GIF 图片。');
    }
    $origExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $blockedExt = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'js', 'html', 'htm', 'shtml'];
    if (in_array($origExt, $blockedExt, true)) {
        throw new InvalidArgumentException('禁止上传可执行脚本文件。');
    }
    return $ext;
}

function cos_register_pending(string $cosPath, int $ownerUserId, ?int $inviteId = null): string
{
    if (!is_cos_path($cosPath)) {
        throw new RuntimeException('无效的对象路径。');
    }
    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION[cos_upload_session_key()]) || !is_array($_SESSION[cos_upload_session_key()])) {
        $_SESSION[cos_upload_session_key()] = [];
    }
    $_SESSION[cos_upload_session_key()][$token] = [
        'path'          => $cosPath,
        'owner_user_id' => $ownerUserId,
        'invite_id'     => $inviteId,
        'expires'       => time() + 3600,
    ];
    return $token;
}

function consume_cos_pending_upload(string $token, int $ownerUserId, ?int $inviteId = null): string
{
    $token = trim($token);
    if ($token === '') {
        throw new RuntimeException('上传凭证无效。');
    }
    $store = $_SESSION[cos_upload_session_key()] ?? [];
    $entry = is_array($store) ? ($store[$token] ?? null) : null;
    if (!is_array($entry) || ($entry['expires'] ?? 0) < time()) {
        throw new RuntimeException('上传凭证无效或已过期，请重新选择图片后再提交。');
    }
    if ($inviteId !== null) {
        if ((int) ($entry['invite_id'] ?? 0) !== $inviteId) {
            throw new RuntimeException('上传凭证与当前链接不匹配。');
        }
    } elseif ((int) ($entry['owner_user_id'] ?? 0) !== $ownerUserId) {
        throw new RuntimeException('上传凭证无效。');
    }
    unset($_SESSION[cos_upload_session_key()][$token]);
    $path = (string) ($entry['path'] ?? '');
    if (!is_cos_path($path)) {
        throw new RuntimeException('上传对象路径无效。');
    }
    return $path;
}

function has_cos_upload_tokens(): bool
{
    return storage_driver() === 'cos'
        && isset($_POST['cos_tokens'])
        && is_array($_POST['cos_tokens'])
        && count(array_filter($_POST['cos_tokens'], static fn($t) => trim((string) $t) !== '')) > 0;
}

function consume_cos_upload_tokens(int $ownerUserId, ?int $inviteId = null): array
{
    $tokens = $_POST['cos_tokens'] ?? [];
    if (!is_array($tokens)) {
        throw new RuntimeException('上传凭证格式错误。');
    }
    $paths = [];
    foreach ($tokens as $token) {
        $token = trim((string) $token);
        if ($token === '') {
            continue;
        }
        $paths[] = consume_cos_pending_upload($token, $ownerUserId, $inviteId);
    }
    if (!$paths) {
        throw new RuntimeException('未收到有效的 COS 上传凭证。');
    }
    return $paths;
}

function consume_cos_logo_token(int $ownerUserId): string
{
    $token = trim((string) ($_POST['cos_logo_token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Logo 上传凭证无效。');
    }
    return consume_cos_pending_upload($token, $ownerUserId);
}

/**
 * @param list<array{name:string,size:int,type:string}> $files
 * @return list<array{token:string,upload_url:string,content_type:string}>
 */
function cos_presign_upload_batch(array $files, string $context, int $ownerUserId, array $opts = []): array
{
    if (storage_driver() !== 'cos') {
        throw new RuntimeException('当前未启用腾讯云 COS。');
    }
    if (!$files) {
        throw new InvalidArgumentException('请选择要上传的文件。');
    }

    $client = cos_client();
    $batch = bin2hex(random_bytes(8));
    $uploads = [];

    foreach ($files as $file) {
        $name = trim((string) ($file['name'] ?? ''));
        $size = (int) ($file['size'] ?? 0);
        $mime = trim((string) ($file['type'] ?? 'application/octet-stream'));
        $ext = cos_validate_file_meta($name, $size, $mime);

        $relative = cos_build_relative_path($context, $ownerUserId, $batch, $ext, $opts);
        $objectKey = $client->objectKey($relative);
        $cosPath = 'cos://' . $objectKey;
        $uploadUrl = $client->getPresignedPutUrl($objectKey, $mime);
        $token = cos_register_pending($cosPath, $ownerUserId, isset($opts['invite_id']) ? (int) $opts['invite_id'] : null);

        $uploads[] = [
            'token'        => $token,
            'upload_url'   => $uploadUrl,
            'content_type' => $mime,
            'cos_path'     => $cosPath,
        ];
    }

    return $uploads;
}

function cos_build_relative_path(string $context, int $ownerUserId, string $batch, string $ext, array $opts): string
{
    $filename = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    return match ($context) {
        'work_create' => 'works/' . $ownerUserId . '/' . $batch . '/' . $filename,
        'character_create' => 'character/' . $ownerUserId . '/' . $batch . '/' . $filename,
        'work_append' => 'works/' . (int) ($opts['work_id'] ?? 0) . '/' . $filename,
        'character_append' => 'character/' . (int) ($opts['character_id'] ?? 0) . '/' . $filename,
        'site_logo' => 'site/' . $filename,
        'artist_work' => 'works/invite/' . (int) ($opts['invite_id'] ?? 0) . '/' . $batch . '/' . $filename,
        default => throw new InvalidArgumentException('无效的上传上下文。'),
    };
}

function cos_json_response(array $payload, int $code = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_has_new_images(): bool
{
    if (has_cos_upload_tokens()) {
        return true;
    }
    $files = reindex_files($_FILES['images'] ?? []);
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }
    return false;
}

/** @return list<string> */
function collect_image_paths_from_request(int $ownerUserId, string $fileSubdir, ?int $inviteId = null): array
{
    $result = ingest_uploaded_images($ownerUserId, $fileSubdir, $inviteId);
    return $result['paths'];
}

/**
 * 从请求读取图片；若中途失败且已有成功项，返回 partial 信息供调用方保存并提示。
 *
 * @return array{paths: list<string>, partial: bool, failed_from: ?int, error: ?string}
 */
function ingest_uploaded_images(int $ownerUserId, string $fileSubdir, ?int $inviteId = null): array
{
    if (has_cos_upload_tokens()) {
        $tokens = $_POST['cos_tokens'] ?? [];
        if (!is_array($tokens)) {
            throw new RuntimeException('上传凭证格式错误。');
        }
        $paths = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $paths[] = consume_cos_pending_upload($token, $ownerUserId, $inviteId);
        }
        if (!$paths) {
            throw new RuntimeException('未收到有效的上传凭证。');
        }

        $partial = !empty($_POST['upload_partial']);
        $failedFrom = max(0, (int) ($_POST['upload_failed_from'] ?? 0));
        $error = trim((string) ($_POST['upload_error_detail'] ?? ''));
        if ($partial && $failedFrom > 0 && $error === '') {
            $error = '上传中断，请重新上传剩余图片。';
        }

        return [
            'paths'       => $paths,
            'partial'     => $partial && $failedFrom > 0,
            'failed_from' => $partial && $failedFrom > 0 ? $failedFrom : null,
            'error'       => $partial && $failedFrom > 0 ? $error : null,
        ];
    }

    $paths = [];
    $files = reindex_files($_FILES['images'] ?? []);
    $hasFile = false;
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $hasFile = true;
            break;
        }
    }
    if (!$hasFile) {
        return [
            'paths'       => [],
            'partial'     => false,
            'failed_from' => null,
            'error'       => null,
        ];
    }

    $fileIndex = 0;
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $fileIndex++;
        try {
            $paths[] = save_uploaded_image($file, $fileSubdir);
        } catch (Throwable $e) {
            if ($paths) {
                return [
                    'paths'       => $paths,
                    'partial'     => true,
                    'failed_from' => $fileIndex,
                    'error'       => user_error_message($e),
                ];
            }
            throw $e;
        }
    }

    if (!$paths) {
        throw new RuntimeException('请至少上传一张图片。');
    }

    return [
        'paths'       => $paths,
        'partial'     => false,
        'failed_from' => null,
        'error'       => null,
    ];
}

function partial_upload_message(int $savedCount, int $failedFrom, string $error): string
{
    return '已成功保存前 ' . $savedCount . ' 张图片。从第 ' . $failedFrom . ' 张起上传失败：'
        . $error . ' 请重新上传剩余图片。';
}

/**
 * 向稿件追加图片并更新封面（若尚无封面）。
 *
 * @param list<string> $paths
 */
function append_work_image_paths(
    PDO $pdo,
    int $workId,
    array $paths,
    array $imageNames,
    array $imageDescs
): void {
    if (!$paths) {
        return;
    }

    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort), -1) FROM work_images WHERE work_id = ?');
    $maxStmt->execute([$workId]);
    $sort = (int) $maxStmt->fetchColumn() + 1;

    $ins = $pdo->prepare(
        'INSERT INTO work_images (work_id, image_path, image_name, image_description, sort)
         VALUES (?, ?, ?, ?, ?)'
    );

    $firstNew = null;
    foreach ($paths as $i => $path) {
        $imgName = trim((string) ($imageNames[$i] ?? '')) ?: ('图片 ' . ($sort + 1));
        $imgDesc = trim((string) ($imageDescs[$i] ?? ''));
        $ins->execute([$workId, $path, $imgName, $imgDesc, $sort]);
        if ($firstNew === null) {
            $firstNew = $path;
        }
        $sort++;
    }

    $fresh = $pdo->prepare('SELECT cover_image FROM works WHERE id = ?');
    $fresh->execute([$workId]);
    if (!$fresh->fetchColumn() && $firstNew !== null) {
        $pdo->prepare('UPDATE works SET cover_image = ? WHERE id = ?')->execute([$firstNew, $workId]);
    }
}

/**
 * 向角色追加主设图并更新封面（若尚无封面）。
 *
 * @param list<string> $paths
 */
function append_character_image_paths(
    PDO $pdo,
    int $characterId,
    array $paths,
    array $imageNames,
    array $imageDescs
): void {
    if (!$paths) {
        return;
    }

    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sort), -1) FROM character_images WHERE character_id = ?');
    $maxStmt->execute([$characterId]);
    $sort = (int) $maxStmt->fetchColumn() + 1;

    $ins = $pdo->prepare(
        'INSERT INTO character_images (character_id, image_path, image_name, description, sort)
         VALUES (?, ?, ?, ?, ?)'
    );

    $firstNew = null;
    foreach ($paths as $i => $path) {
        $imgName = trim((string) ($imageNames[$i] ?? '')) ?: ('主设图 ' . ($sort + 1));
        $imgDesc = trim((string) ($imageDescs[$i] ?? ''));
        $ins->execute([$characterId, $path, $imgName, $imgDesc, $sort]);
        if ($firstNew === null) {
            $firstNew = $path;
        }
        $sort++;
    }

    $fresh = $pdo->prepare('SELECT cover_image FROM characters WHERE id = ?');
    $fresh->execute([$characterId]);
    if (!$fresh->fetchColumn() && $firstNew !== null) {
        $pdo->prepare('UPDATE characters SET cover_image = ? WHERE id = ?')->execute([$firstNew, $characterId]);
    }
}
