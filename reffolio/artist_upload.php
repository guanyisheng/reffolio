<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$invite = find_invite_by_token($token);
$error = '';
$done = false;
$workId = 0;
$continueWorkId = (int) ($_GET['continue_work'] ?? $_POST['continue_work'] ?? 0);
$continueWork = null;

if (!$invite) {
    render_header('上传链接无效', ['body_class' => 'page-share']);
    echo '<div class="container"><div class="empty-state"><p>上传链接无效或不存在。</p></div></div>';
    render_footer();
    exit;
}

$usable = invite_is_usable($invite);
$ownerName = $invite['owner_name'] ?: $invite['owner_username'];
$charName = row_str($invite, 'character_name') ?: '未命名角色';

if ($continueWorkId > 0) {
    $cwStmt = $pdo->prepare(
        'SELECT id, title FROM works WHERE id = ? AND character_id = ? LIMIT 1'
    );
    $cwStmt->execute([$continueWorkId, (int) $invite['character_id']]);
    $continueWork = $cwStmt->fetch() ?: null;
    if (!$continueWork) {
        $continueWorkId = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usable) {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '上传内容过大或被服务器截断，请减小图片后重试。';
    } else {
        verify_csrf();

        // 再次校验（防并发超次数）
        $invite = find_invite_by_token($token);
        if (!$invite || !invite_is_usable($invite)) {
            $error = $invite ? invite_unusable_reason($invite) : '上传链接无效。';
            $usable = false;
        } else {
            $action = (string) ($_POST['action'] ?? 'create');

            if ($action === 'append_images') {
                $appendWorkId = (int) ($_POST['continue_work'] ?? 0);
                $cwStmt = $pdo->prepare(
                    'SELECT id FROM works WHERE id = ? AND character_id = ? LIMIT 1'
                );
                $cwStmt->execute([$appendWorkId, (int) $invite['character_id']]);
                if (!$cwStmt->fetchColumn()) {
                    $error = '稿件不存在，无法继续上传。';
                } elseif (!request_has_new_images()) {
                    $error = '请选择要上传的图片。';
                } else {
                    try {
                        $pdo->beginTransaction();
                        $ingest = ingest_uploaded_images(
                            (int) $invite['user_id'],
                            'works/' . $appendWorkId,
                            (int) $invite['id']
                        );
                        append_work_image_paths(
                            $pdo,
                            $appendWorkId,
                            $ingest['paths'],
                            $_POST['image_names'] ?? [],
                            $_POST['image_descriptions'] ?? []
                        );
                        $pdo->commit();

                        if ($ingest['partial']) {
                            flash(
                                'warning',
                                partial_upload_message(
                                    count($ingest['paths']),
                                    (int) $ingest['failed_from'],
                                    (string) $ingest['error']
                                )
                            );
                            redirect('/artist_upload.php?token=' . urlencode($token) . '&continue_work=' . $appendWorkId);
                        }

                        bump_invite_use((int) $invite['id']);
                        $done = true;
                        $workId = $appendWorkId;
                        $usable = invite_is_usable(find_invite_by_token($token) ?? $invite);
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = user_error_message($e);
                    }
                }
            } else {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $date = trim((string) ($_POST['date'] ?? ''));
            $artistName = trim((string) ($_POST['artist_name'] ?? ''));
            $categoryId = resolve_work_category_id((int) $invite['user_id'], $_POST['category_id'] ?? 0);
            $imageNames = $_POST['image_names'] ?? [];
            $imageDescs = $_POST['image_descriptions'] ?? [];

            if ($title === '') {
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

                        if ($artistName !== '') {
                            $description = trim(
                                ($description !== '' ? $description . "\n\n" : '')
                                . '画师：' . $artistName
                            );
                        }

                        $characterId = (int) $invite['character_id'];
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

                        $ingest = ingest_uploaded_images(
                            (int) $invite['user_id'],
                            'works/' . $workId,
                            (int) $invite['id']
                        );
                        append_work_image_paths($pdo, $workId, $ingest['paths'], $imageNames, $imageDescs);

                        if ($ingest['partial']) {
                            $pdo->commit();
                            flash(
                                'warning',
                                partial_upload_message(
                                    count($ingest['paths']),
                                    (int) $ingest['failed_from'],
                                    (string) $ingest['error']
                                )
                            );
                            redirect('/artist_upload.php?token=' . urlencode($token) . '&continue_work=' . $workId);
                        }

                        bump_invite_use((int) $invite['id']);
                        $pdo->commit();
                        $done = true;
                        $usable = invite_is_usable(find_invite_by_token($token) ?? $invite);
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
    }
}

render_header('画师上传 · ' . $charName, ['body_class' => 'page-share']);
?>
<div class="container">
  <span class="share-badge">画师上传入口 · 无需登录</span>

  <section class="char-hero" style="margin-bottom:1.5rem;">
    <div class="char-avatar">
      <img src="<?= e(image_url(character_avatar_path($invite))) ?>" alt="<?= e($charName) ?>">
    </div>
    <div class="char-meta">
      <h1><?= e($charName) ?></h1>
      <p class="desc" style="margin-bottom:0.35rem;">委托方：<?= e($ownerName) ?></p>
      <?php if (!empty($invite['note'])): ?>
        <p class="desc"><?= e($invite['note']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($done): ?>
    <div class="flash flash-success">
      上传成功！稿件「<?= e($_POST['title'] ?? '') ?>」已提交给委托方。
      <?php if ($workId): ?>
        <br><a href="/share_work.php?id=<?= $workId ?>" target="_blank">查看已提交的稿件（公开页）</a>
      <?php endif; ?>
    </div>
    <?php if ($usable): ?>
      <p class="page-lead">此链接仍可继续使用，如需再传一件请刷新页面。</p>
      <div class="btn-row">
        <a class="btn btn-primary" href="/artist_upload.php?token=<?= e(urlencode($token)) ?>">继续上传</a>
      </div>
    <?php endif; ?>
  <?php elseif (!$usable): ?>
    <div class="flash flash-error"><?= e(invite_unusable_reason($invite)) ?></div>
  <?php elseif ($continueWork): ?>
    <?php if ($error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <p class="page-lead">稿件「<?= e($continueWork['title']) ?>」部分图片已保存，请继续上传剩余图片。</p>
    <form method="post" enctype="multipart/form-data" data-upload-progress data-cos-context="artist_work">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <input type="hidden" name="action" value="append_images">
      <input type="hidden" name="continue_work" value="<?= (int) $continueWork['id'] ?>">
      <div class="form-row">
        <label for="work-images">上传剩余图片</label>
        <input id="work-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
        <span class="hint">只需选择尚未成功上传的图片，单张最大 <?= e(upload_max_bytes_human()) ?></span>
      </div>
      <div id="character-remark-fields" class="preview-list"></div>
      <div class="btn-row">
        <button type="submit" class="btn btn-primary">继续上传</button>
      </div>
    </form>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form id="upload-wizard" method="post" enctype="multipart/form-data" data-upload-progress data-cos-context="artist_work">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <div class="wizard-steps" aria-hidden="true">
        <div class="wizard-step active"><span class="n">1</span>稿件信息</div>
        <div class="wizard-step"><span class="n">2</span>上传图片</div>
        <div class="wizard-step"><span class="n">3</span>图片备注</div>
      </div>

      <div class="wizard-panel active" data-step="info">
        <div class="form-grid">
          <div class="form-row">
            <label for="artist_name">你的署名（可选）</label>
            <input id="artist_name" name="artist_name" type="text" maxlength="64" value="<?= e($_POST['artist_name'] ?? '') ?>" placeholder="会写入稿件说明">
          </div>
          <div class="form-row">
            <label for="title">稿件标题</label>
            <input id="title" name="title" type="text" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>" placeholder="例如：夏日泳装">
          </div>
          <div class="form-row">
            <label for="date">日期</label>
            <input id="date" name="date" type="date" value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>">
          </div>
          <?php render_category_select((int) $invite['user_id'], (int) ($_POST['category_id'] ?? 0)); ?>
          <div class="form-row">
            <label for="description">稿件说明</label>
            <textarea id="description" name="description" placeholder="可选补充说明"><?= e($_POST['description'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="btn-row">
          <button type="button" class="btn btn-primary" data-next>下一步</button>
        </div>
      </div>

      <div class="wizard-panel" data-step="images">
        <div class="form-row">
          <label for="work-images">上传多张图片</label>
          <input id="work-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
          <span class="hint">支持 JPG / PNG / WEBP / GIF，单张最大 <?= e(upload_max_bytes_human()) ?><?php if (storage_driver() === 'cos'): ?> · COS 直传<?php endif; ?></span>
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
          <button type="submit" class="btn btn-primary">提交上传</button>
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
