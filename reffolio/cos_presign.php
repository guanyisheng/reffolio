<?php
/**
 * 腾讯云 COS 浏览器直传 — 申请预签名 URL
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once ROOT_PATH . '/includes/cos_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cos_json_response(['ok' => false, 'message' => '仅支持 POST'], 405);
}

if (storage_driver() !== 'cos') {
    cos_json_response(['ok' => false, 'message' => '当前存储方式为本地，无需直传 COS。'], 400);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    cos_json_response(['ok' => false, 'message' => '无效的请求令牌'], 403);
}

$context = trim((string) ($_POST['context'] ?? ''));
$filesRaw = (string) ($_POST['files'] ?? '[]');
$files = json_decode($filesRaw, true);
if (!is_array($files)) {
    cos_json_response(['ok' => false, 'message' => '文件列表格式错误'], 400);
}

$ownerUserId = 0;
$opts = [];

try {
    switch ($context) {
        case 'work_create':
        case 'character_create':
            $user = require_login();
            $ownerUserId = (int) $user['id'];
            break;

        case 'work_append':
            $user = require_login();
            $ownerUserId = (int) $user['id'];
            $workId = (int) ($_POST['work_id'] ?? 0);
            if ($workId <= 0 || !user_owns_work($ownerUserId, $workId)) {
                throw new InvalidArgumentException('无效的稿件。');
            }
            $opts['work_id'] = $workId;
            break;

        case 'character_append':
            $user = require_login();
            $ownerUserId = (int) $user['id'];
            $characterId = (int) ($_POST['character_id'] ?? 0);
            if ($characterId <= 0 || !user_owns_character($ownerUserId, $characterId)) {
                throw new InvalidArgumentException('无效的角色。');
            }
            $opts['character_id'] = $characterId;
            break;

        case 'site_logo':
            $user = require_admin();
            $ownerUserId = (int) $user['id'];
            if (count($files) > 1) {
                throw new InvalidArgumentException('Logo 只能上传一张图片。');
            }
            break;

        case 'artist_work':
            $inviteToken = trim((string) ($_POST['invite_token'] ?? ''));
            $invite = find_invite_by_token($inviteToken);
            if (!$invite || !invite_is_usable($invite)) {
                throw new InvalidArgumentException($invite ? invite_unusable_reason($invite) : '上传链接无效。');
            }
            $ownerUserId = (int) $invite['user_id'];
            $opts['invite_id'] = (int) $invite['id'];
            break;

        default:
            throw new InvalidArgumentException('无效的上传上下文。');
    }

    $uploads = cos_presign_upload_batch($files, $context, $ownerUserId, $opts);
    cos_json_response([
        'ok'      => true,
        'uploads' => array_map(static fn(array $u): array => [
            'token'        => $u['token'],
            'upload_url'   => $u['upload_url'],
            'content_type' => $u['content_type'],
        ], $uploads),
    ]);
} catch (InvalidArgumentException $e) {
    cos_json_response(['ok' => false, 'message' => user_error_message($e)], 400);
} catch (Throwable $e) {
    cos_json_response(['ok' => false, 'message' => user_error_message($e)], 500);
}
