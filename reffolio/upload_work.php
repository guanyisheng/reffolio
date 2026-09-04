<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$error = '';
$preselect = (int) ($_GET['character_id'] ?? 0);

$charStmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name ASC');
$charStmt->execute([(int) $user['id']]);
$characters = $charStmt->fetchAll();

if (!$characters) {
    flash('info', '请先创建角色设定，再上传稿件。');
    redirect('/create_character.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '上传内容过大或被服务器截断，请减小图片后重试。';
    } else {
    verify_csrf();

    $characterId = (int) ($_POST['character_id'] ?? 0);
    $categoryId = resolve_work_category_id((int) $user['id'], $_POST['category_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $date = trim((string) ($_POST['date'] ?? ''));
    $imageNames = $_POST['image_names'] ?? [];
    $imageDescs = $_POST['image_descriptions'] ?? [];

    if (!user_owns_character((int) $user['id'], $characterId)) {
        $error = '请选择有效的角色。';
    } elseif ($title === '') {
        $error = '请填写稿件标题。';
    } elseif ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $error = '日期格式不正确。';
    } else {
        $hasImages = request_has_new_images();
        if (!$hasImages) {
            $error = '请至少上传一张图片。';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO works (character_id, category_id, title, description, date, cover_image)
                     VALUES (?, ?, ?, ?, ?, NULL)'
                );
                $stmt->execute([
                    $characterId,
                    $categoryId,
                    $title,
                    $description,
                    $date !== '' ? $date : null,
                ]);
                $workId = (int) $pdo->lastInsertId();

                $ingest = ingest_uploaded_images((int) $user['id'], 'works/' . $workId);
                append_work_image_paths($pdo, $workId, $ingest['paths'], $imageNames, $imageDescs);

                $pdo->commit();

                if ($ingest['partial']) {
                    flash(
                        'warning',
                        partial_upload_message(count($ingest['paths']), (int) $ingest['failed_from'], (string) $ingest['error'])
                    );
                    redirect('/edit_work.php?id=' . $workId);
                }

                flash('success', '稿件「' . $title . '」已上传。');
                redirect('/work.php?id=' . $workId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = user_error_message($e);
            }
        }
    }
    }
}

render_header('上传稿件');
?>
<div class="container">
  <?php render_page_back('/works.php', '全部稿件'); ?>
  <h1 class="page-title">上传稿件</h1>
  <p class="page-lead">四步完成：选择角色 → 填写信息 → 上传图片 → 填写备注。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form id="upload-wizard" method="post" enctype="multipart/form-data" data-upload-progress data-cos-context="work_create">
    <?= csrf_field() ?>

    <div class="wizard-steps" aria-hidden="true">
      <div class="wizard-step active"><span class="n">1</span>选择角色</div>
      <div class="wizard-step"><span class="n">2</span>稿件信息</div>
      <div class="wizard-step"><span class="n">3</span>上传图片</div>
      <div class="wizard-step"><span class="n">4</span>图片备注</div>
    </div>

    <div class="wizard-panel active" data-step="character">
      <div class="form-row">
        <label for="character_id">所属角色</label>
        <select id="character_id" name="character_id" required>
          <option value="">请选择角色</option>
          <?php foreach ($characters as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($preselect === (int) $c['id'] || (int) ($_POST['character_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="btn-row">
        <button type="button" class="btn btn-primary" data-next>下一步</button>
      </div>
    </div>

    <div class="wizard-panel" data-step="info">
      <div class="form-grid">
        <div class="form-row">
          <label for="title">稿件标题</label>
          <input id="title" name="title" type="text" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>" placeholder="例如：夏日泳装">
        </div>
        <div class="form-row">
          <label for="date">日期</label>
          <input id="date" name="date" type="date" value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>">
        </div>
        <?php render_category_select((int) $user['id'], (int) ($_POST['category_id'] ?? 0)); ?>
        <div class="form-row">
          <label for="description">稿件说明</label>
          <textarea id="description" name="description" placeholder="夏日主题委托稿。"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="btn-row">
        <button type="button" class="btn btn-ghost" data-prev>上一步</button>
        <button type="button" class="btn btn-primary" data-next>下一步</button>
      </div>
    </div>

    <div class="wizard-panel" data-step="images">
      <div class="form-row">
        <label for="work-images">上传多张图片</label>
        <input id="work-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
        <span class="hint">支持 JPG / PNG / WEBP / GIF，单张最大 <?= e(upload_max_bytes_human()) ?><?php if (storage_driver() === 'cos'): ?> · COS 直传，不经过服务器<?php endif; ?></span>
      </div>
      <div id="image-preview" class="preview-list"></div>
      <div class="btn-row">
        <button type="button" class="btn btn-ghost" data-prev>上一步</button>
        <button type="button" class="btn btn-primary" data-next>下一步</button>
      </div>
    </div>

    <div class="wizard-panel" data-step="remarks">
      <p class="hint" style="margin-top:0;">为每张图片填写名称与备注：</p>
      <div id="remark-fields" class="preview-list"></div>
      <div class="btn-row">
        <button type="button" class="btn btn-ghost" data-prev>上一步</button>
        <button type="submit" class="btn btn-primary">完成上传</button>
      </div>
    </div>
  </form>
</div>
<?php render_footer(); ?>
