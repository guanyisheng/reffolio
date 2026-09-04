<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 上传过大时 PHP 可能清空 $_POST / $_FILES
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '上传内容过大或被服务器截断，请减小图片后重试。';
    } else {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $tags = tags_to_string(parse_tags((string) ($_POST['tags'] ?? '')));
    $imageNames = $_POST['image_names'] ?? [];
    $imageDescs = $_POST['image_descriptions'] ?? [];

    if ($name === '') {
        $error = '请填写角色名称。';
    } else {
        $files = reindex_files($_FILES['images'] ?? []);
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO characters (user_id, name, description, tags, cover_image)
                 VALUES (?, ?, ?, ?, NULL)'
            );
            $stmt->execute([(int) $user['id'], $name, $description, $tags]);
            $characterId = (int) $pdo->lastInsertId();
            if ($characterId <= 0) {
                throw new RuntimeException('创建角色失败。');
            }

            $ingest = ingest_uploaded_images((int) $user['id'], 'character/' . $characterId);
            append_character_image_paths(
                $pdo,
                $characterId,
                $ingest['paths'],
                $imageNames,
                $imageDescs
            );

            $pdo->commit();

            if ($ingest['partial']) {
                flash(
                    'warning',
                    partial_upload_message(count($ingest['paths']), (int) $ingest['failed_from'], (string) $ingest['error'])
                );
                redirect('/edit_character.php?id=' . $characterId);
            }

            flash('success', '角色「' . $name . '」已创建。');
            redirect('/character.php?id=' . $characterId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = user_error_message($e);
        }
    }
    }
}

render_header('新建角色');
?>
<div class="container">
  <?php render_page_back('/characters.php', '我的角色'); ?>
  <h1 class="page-title">新建角色设定</h1>
  <p class="page-lead">填写角色信息，并上传主设图片（可多张，每张可单独备注）。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="wizard-panel active" style="display:block;" data-upload-progress data-cos-context="character_create">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-row">
        <label for="name">角色名称</label>
        <input id="name" name="name" type="text" required maxlength="128" value="<?= e($_POST['name'] ?? '') ?>" placeholder="例如：路南">
      </div>
      <div class="form-row">
        <label for="description">角色简介</label>
        <textarea id="description" name="description" placeholder="一只喜欢摄影和计算机的小猫兽。"><?= e($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label for="tags">标签</label>
        <input id="tags" name="tags" type="text" value="<?= e($_POST['tags'] ?? '') ?>" placeholder="猫兽, 蓝色, 少年（逗号分隔）">
        <span class="hint">多个标签用逗号或空格分隔</span>
      </div>
      <div class="form-row">
        <label for="character-images">主设图片（可多选）</label>
        <input id="character-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
        <span class="hint">支持 JPG / PNG / WEBP / GIF，单张最大 <?= e(upload_max_bytes_human()) ?></span>
      </div>
    </div>

    <div id="character-remark-fields" class="preview-list"></div>

    <div class="btn-row">
      <button class="btn btn-primary" type="submit">创建角色</button>
      <a class="btn btn-ghost" href="/characters.php">取消</a>
    </div>
  </form>
</div>
<?php render_footer(); ?>
