<?php
/**
 * 腾讯云 COS 连通性测试（登录 + CSRF）
 * 使用当前表单参数上传并删除一个小测试文件
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function json_out(array $payload, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => '仅支持 POST'], 405);
}

if (current_user() === null) {
    json_out(['ok' => false, 'message' => '请先登录'], 401);
}

if (!is_admin(current_user())) {
    json_out(['ok' => false, 'message' => '仅管理员可测试存储连接'], 403);
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    json_out(['ok' => false, 'message' => '无效的请求令牌'], 403);
}

$secretId  = trim((string) ($_POST['secret_id'] ?? ''));
$secretKey = trim((string) ($_POST['secret_key'] ?? ''));
$bucket    = trim((string) ($_POST['bucket'] ?? ''));
$region    = trim((string) ($_POST['region'] ?? ''));
$domain    = trim((string) ($_POST['domain'] ?? ''));
$acl       = trim((string) ($_POST['acl'] ?? 'private')) ?: 'private';
$scheme    = trim((string) ($_POST['scheme'] ?? 'https')) ?: 'https';
$prefix    = trim((string) ($_POST['prefix'] ?? ''));
$cdnDomain = trim((string) ($_POST['cdn_domain'] ?? ''));

if ($secretId === '') {
    $secretId = (string) get_setting('storage.cos.secret_id', '');
}
if ($secretKey === '') {
    $secretKey = (string) get_setting('storage.cos.secret_key', '');
}
if ($bucket === '') {
    $bucket = (string) get_setting('storage.cos.bucket', '');
}
if ($region === '') {
    $region = (string) get_setting('storage.cos.region', '');
}
if ($domain === '') {
    $domain = (string) get_setting('storage.cos.domain', '');
}
if ($prefix === '' && get_setting('storage.cos.prefix') !== null) {
    $prefix = (string) get_setting('storage.cos.prefix', '');
}

if ($secretId === '' || $secretKey === '') {
    json_out(['ok' => false, 'message' => '请填写 SecretId 与 SecretKey（SK 若已保存可留空）'], 422);
}
if ($bucket === '' || $region === '' || $domain === '') {
    json_out(['ok' => false, 'message' => '请填写存储桶名称、地域、存储桶域名'], 422);
}

$tmp = tempnam(sys_get_temp_dir(), 'costest_');
if ($tmp === false) {
    json_out(['ok' => false, 'message' => '无法创建临时文件'], 500);
}

$payload = 'reffolio-cos-healthcheck ' . date('c') . ' ' . bin2hex(random_bytes(4));
if (file_put_contents($tmp, $payload) === false) {
    @unlink($tmp);
    json_out(['ok' => false, 'message' => '写入临时文件失败'], 500);
}

try {
    $client = new TencentCosClient([
        'secret_id'  => $secretId,
        'secret_key' => $secretKey,
        'bucket'     => $bucket,
        'region'     => $region,
        'domain'     => $domain,
        'acl'        => $acl,
        'scheme'     => $scheme,
        'prefix'     => $prefix,
        'cdn_domain' => $cdnDomain,
        'signed_url_expires' => 600,
    ]);

    $relative = '_healthcheck/test_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.txt';
    $objectKey = $client->objectKey($relative);
    $client->putObject($tmp, $objectKey, 'text/plain; charset=utf-8');

    $deleted = true;
    try {
        $client->deleteObject($objectKey);
    } catch (Throwable $e) {
        $deleted = false;
    }

    json_out([
        'ok'      => true,
        'message' => $deleted
            ? '连接成功：已上传并清理测试文件。'
            : '连接成功：已上传测试文件（清理失败，可手动删除）。',
        'object'  => $objectKey,
    ]);
} catch (Throwable $e) {
    json_out([
        'ok'      => false,
        'message' => user_error_message($e),
    ], 400);
} finally {
    @unlink($tmp);
}
