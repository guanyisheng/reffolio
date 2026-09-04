<?php
/**
 * 应用引导：Session、PDO、公共函数
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_MAX_BYTES', 50 * 1024 * 1024); // 50MB
define('ALLOWED_MIME', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
]);

require_once ROOT_PATH . '/includes/functions.php';

$config = require ROOT_PATH . '/config/database.php';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_CASE               => PDO::CASE_LOWER,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('数据库连接失败：' . user_error_message($e));
}

send_no_cache_headers();
require_once ROOT_PATH . '/includes/storage.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/invites.php';
require_once ROOT_PATH . '/includes/categories.php';
require_once ROOT_PATH . '/includes/site.php';
require_once ROOT_PATH . '/includes/cos_upload.php';
