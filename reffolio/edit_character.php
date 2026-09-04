<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

if ($id <= 0 || !user_owns_character((int) $user['id'], $id)) {
    flash('error', '角色不存在或无权访问。');
    redirect('/characters.php');
}

$stmt = $pdo->prepare(
    'SELECT id, user_id, name, description, tags, cover_image, avatar_image, create_time, update_time
     FROM characters WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$id, (int) $user['id']]);
$character = $stmt->fetch();
if (!$character) {
    flash('error', '角色不存在。');
    redirect('/characters.php');
}

function load_character_images(PDO $pdo, int $characterId): array
{
    $imgStmt = $pdo->prepare('SELECT * FROM character_images WHERE character_id = ? ORDER BY sort ASC, id ASC');
    $imgStmt->execute([$characterId]);
    return $imgStmt->fetchAll();
}

$images = load_character_images($pdo, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '上传内容过大或被服务器截断，请减小图片后重试。';
    } else {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'save');

        try {
            if ($action === 'delete_image') {
                $imageId = (int) ($_POST['image_id'] ?? 0);
                $del = $pdo->prepare('SELECT * FROM character_images WHERE id = ? AND character_id = ? LIMIT 1');
                $del->execute([$imageId, $id]);
                $row = $del->fetch();
                if ($row) {
                    delete_upload_file((string) $row['image_path']);
                    $pdo->prepare('DELETE FROM character_images WHERE id = ?')->execute([$imageId]);
                    if (($character['cover_image'] ?? '') === $row['image_path']) {
                        $next = $pdo->prepare('SELECT image_path FROM character_images WHERE character_id = ? ORDER BY sort ASC, id ASC LIMIT 1');
                        $next->execute([$id]);
                        $newCover = $next->fetchColumn() ?: null;
                        $pdo->prepare('UPDATE characters SET cover_image = ? WHERE id = ?')->execute([$newCover, $id]);
                    }
                    if (($character['avatar_image'] ?? '') === $row['image_path']) {
                        $pdo->prepare('UPDATE characters SET avatar_image = NULL WHERE id = ?')->execute([$id]);
                    }
                    flash('success', '已删除图片。');
                    redirect('/edit_character.php?id=' . $id);
                }
                throw new InvalidArgumentException('图片不存在。');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $tags = tags_to_string(parse_tags((string) ($_POST['tags'] ?? '')));
            if ($name === '') {
                throw new InvalidArgumentException('请填写角色名称。');
            }

            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE characters SET name = ?, description = ?, tags = ? WHERE id = ? AND user_id = ?'
            )->execute([$name, $description, $tags, $id, (int) $user['id']]);

            $editIds = $_POST['existing_ids'] ?? [];
            $editNames = $_POST['existing_names'] ?? [];
            $editDescs = $_POST['existing_descriptions'] ?? [];
            if (is_array($editIds)) {
                $upd = $pdo->prepare(
                    'UPDATE character_images SET image_name = ?, description = ? WHERE id = ? AND character_id = ?'
                );
                foreach ($editIds as $i => $imgId) {
                    $imgId = (int) $imgId;
                    if ($imgId <= 0) {
                        continue;
                    }
                    $upd->execute([
                        trim((string) ($editNames[$i] ?? '')),
                        trim((string) ($editDescs[$i] ?? '')),
                        $imgId,
                        $id,
                    ]);
                }
            }

            $coverId = (int) ($_POST['cover_image_id'] ?? 0);
            $coverPath = null;
            if ($coverId > 0) {
                $cStmt = $pdo->prepare('SELECT image_path FROM character_images WHERE id = ? AND character_id = ? LIMIT 1');
                $cStmt->execute([$coverId, $id]);
                $coverPath = $cStmt->fetchColumn() ?: null;
                if ($coverPath) {
                    $pdo->prepare('UPDATE characters SET cover_image = ? WHERE id = ?')->execute([$coverPath, $id]);
                }
            }

            $avatarId = (int) ($_POST['avatar_image_id'] ?? 0);
            if ($avatarId > 0) {
                $aStmt = $pdo->prepare('SELECT image_path FROM character_images WHERE id = ? AND character_id = ? LIMIT 1');
                $aStmt->execute([$avatarId, $id]);
                $avatarPath = $aStmt->fetchColumn() ?: null;
                if ($avatarPath) {
                    if ($coverPath === null) {
                        $freshCover = $pdo->prepare('SELECT cover_image FROM characters WHERE id = ?');
                        $freshCover->execute([$id]);
                        $coverPath = $freshCover->fetchColumn() ?: null;
                    }
                    $avatarVal = ($avatarPath === $coverPath) ? null : $avatarPath;
                    $pdo->prepare('UPDATE characters SET avatar_image = ? WHERE id = ?')->execute([$avatarVal, $id]);
                }
            }

            $ingest = ingest_uploaded_images((int) $user['id'], 'character/' . $id);
            append_character_image_paths(
                $pdo,
                $id,
                $ingest['paths'],
                $_POST['image_names'] ?? [],
                $_POST['image_descriptions'] ?? []
            );

            $pdo->commit();

            if ($ingest['partial']) {
                flash(
                    'warning',
                    partial_upload_message(count($ingest['paths']), (int) $ingest['failed_from'], (string) $ingest['error'])
                );
                redirect('/edit_character.php?id=' . $id);
            }

            flash('success', '角色信息已更新。');
            redirect('/character.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = user_error_message($e);
            $character['name'] = $_POST['name'] ?? $character['name'];
            $character['description'] = $_POST['description'] ?? $character['description'];
            $character['tags'] = $_POST['tags'] ?? $character['tags'];
            $images = load_character_images($pdo, $id);
        }
    }
}

render_header('编辑角色');
?>
<div class="container">
  <?php render_page_back('/character.php?id=' . $id, '返回角色'); ?>
  <h1 class="page-title">编辑角色</h1>
  <p class="page-lead">修改「<?= e(display_character_name($character)) ?>」的基本信息与主设备注。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="wizard-panel active" style="display:block;max-width:760px;" data-upload-progress data-cos-context="character_append" data-cos-character-id="<?= $id ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="save">

    <div class="form-grid">
      <div class="form-row">
        <label for="name">角色名称</label>
        <input id="name" name="name" type="text" required maxlength="128" value="<?= e(row_str($character, 'name')) ?>">
      </div>
      <div class="form-row">
        <label for="description">角色简介</label>
        <textarea id="description" name="description"><?= e($character['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <label for="tags">标签</label>
        <input id="tags" name="tags" type="text" value="<?= e($character['tags'] ?? '') ?>" placeholder="猫兽, 蓝色, 少年">
      </div>
    </div>

    <h2 class="section-title">主设图片</h2>
    <p class="hint">可分别指定「封面」（列表卡片）与「头像」（角色页 char-avatar）；未单独设头像时使用封面。</p>
    <?php if (!$images): ?>
      <p class="hint">暂无主设图，可在下方追加上传。</p>
    <?php else: ?>
      <div class="preview-list">
        <?php foreach ($images as $img): ?>
          <div class="preview-item" style="grid-template-columns:96px 1fr;">
            <img src="<?= e(image_url($img['image_path'])) ?>" alt="">
            <div class="form-grid">
              <input type="hidden" name="existing_ids[]" value="<?= (int) $img['id'] ?>">
              <div class="form-row">
                <label>名称</label>
                <input type="text" name="existing_names[]" value="<?= e($img['image_name'] ?? '') ?>">
              </div>
              <div class="form-row">
                <label>备注</label>
                <textarea name="existing_descriptions[]" rows="2"><?= e($img['description'] ?? '') ?></textarea>
              </div>
              <div class="cover-avatar-picks">
                <label class="cover-avatar-pick">
                  <input type="radio" name="cover_image_id" value="<?= (int) $img['id'] ?>"
                    <?= (($character['cover_image'] ?? '') === $img['image_path']) ? 'checked' : '' ?>>
                  设为封面
                </label>
                <label class="cover-avatar-pick">
                  <input type="radio" name="avatar_image_id" value="<?= (int) $img['id'] ?>"
                    <?= character_uses_image_as_avatar($character, (string) $img['image_path']) ? 'checked' : '' ?>>
                  设为头像
                </label>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="section-title">追加主设图</h2>
    <div class="form-row">
      <label for="character-images">选择图片（可多选）</label>
      <input id="character-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
      <span class="hint">单张最大 <?= e(upload_max_bytes_human()) ?></span>
    </div>
    <div id="character-remark-fields" class="preview-list"></div>

    <div class="btn-row">
      <button class="btn btn-primary" type="submit">保存</button>
      <a class="btn btn-ghost" href="/character.php?id=<?= $id ?>">返回</a>
    </div>
  </form>

  <?php if ($images): ?>
    <h2 class="section-title">删除图片</h2>
    <div class="preview-list" style="max-width:760px;">
      <?php foreach ($images as $img): ?>
        <form method="post" class="preview-item" style="grid-template-columns:72px 1fr auto;align-items:center;"
              onsubmit="return confirm('确定删除这张图片？');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="action" value="delete_image">
          <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
          <img src="<?= e(image_url($img['image_path'])) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;">
          <div>
            <strong><?= e($img['image_name'] ?: '未命名') ?></strong>
          </div>
          <button class="btn btn-ghost btn-sm" type="submit">删除</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
