<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/works.php');
}

verify_csrf();

$workId = (int) ($_POST['work_id'] ?? 0);
$imageIds = $_POST['image_ids'] ?? [];

if ($workId <= 0 || !is_array($imageIds) || !$imageIds) {
    flash('error', '请先勾选要下载的图片。');
    redirect('/work.php?id=' . max($workId, 0));
}

if (!user_owns_work((int) $user['id'], $workId)) {
    flash('error', '无权下载该稿件。');
    redirect('/works.php');
}

$ids = array_values(array_unique(array_map('intval', $imageIds)));
$ids = array_filter($ids, static fn($v) => $v > 0);
if (!$ids) {
    flash('error', '请先勾选要下载的图片。');
    redirect('/work.php?id=' . $workId);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = $ids;
array_unshift($params, $workId);

$stmt = $pdo->prepare(
    "SELECT id, image_path, image_name
     FROM work_images
     WHERE work_id = ? AND id IN ($placeholders)
     ORDER BY sort ASC, id ASC"
);
$stmt->execute($params);
$images = $stmt->fetchAll();

if (!$images) {
    flash('error', '未找到可下载的图片。');
    redirect('/work.php?id=' . $workId);
}

if (!class_exists('ZipArchive')) {
    flash('error', '服务器未启用 ZipArchive 扩展，无法打包下载。');
    redirect('/work.php?id=' . $workId);
}

$zip = new ZipArchive();
$tmpZip = tempnam(sys_get_temp_dir(), 'workzip_');
if ($tmpZip === false || $zip->open($tmpZip, ZipArchive::OVERWRITE) !== true) {
    flash('error', '无法创建 ZIP 文件。');
    redirect('/work.php?id=' . $workId);
}

$usedNames = [];
$tempFiles = [];
$failedPaths = [];

foreach ($images as $img) {
    $resolved = resolve_storage_to_local((string) $img['image_path']);
    if ($resolved === null) {
        $failedPaths[] = (string) $img['image_path'];
        continue;
    }
    [$localFile, $shouldUnlink] = $resolved;
    if ($shouldUnlink) {
        $tempFiles[] = $localFile;
    }

    $ext = pathinfo((string) $img['image_path'], PATHINFO_EXTENSION);
    if ($ext === '' || strlen($ext) > 8) {
        $ext = pathinfo($localFile, PATHINFO_EXTENSION);
    }
    $base = preg_replace('/[^\w\x{4e00}-\x{9fa5}\-.]+/u', '_', (string) ($img['image_name'] ?: 'image')) ?: 'image';
    $entry = $base . ($ext ? '.' . $ext : '');
    $n = 1;
    while (isset($usedNames[$entry])) {
        $entry = $base . '_' . $n . ($ext ? '.' . $ext : '');
        $n++;
    }
    $usedNames[$entry] = true;
    $zip->addFile($localFile, $entry);
}

$zip->close();

foreach ($tempFiles as $f) {
    @unlink($f);
}

if (!is_file($tmpZip) || filesize($tmpZip) === 0) {
    @unlink($tmpZip);
    $detail = $failedPaths
        ? '以下文件无法读取：' . implode('；', array_slice($failedPaths, 0, 5)) . (count($failedPaths) > 5 ? '…' : '')
        : 'ZIP 内未包含任何文件。';
    flash('error', '打包失败：' . $detail);
    redirect('/work.php?id=' . $workId);
}

$filename = 'work_' . $workId . '_images.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($tmpZip));
header('Cache-Control: no-store');
readfile($tmpZip);
@unlink($tmpZip);
exit;
