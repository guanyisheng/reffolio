<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';

if ($id <= 0 || !user_owns_work((int) $user['id'], $id)) {
    flash('error', '稿件不存在或无权访问。');
    redirect('/works.php');
}

$stmt = $pdo->prepare(
    'SELECT w.*, c.name AS character_name, c.id AS character_id
     FROM works w
     INNER JOIN characters c ON c.id = w.character_id
     WHERE w.id = ? AND c.user_id = ? LIMIT 1'
);
$stmt->execute([$id, (int) $user['id']]);
$work = $stmt->fetch();
if (!$work) {
    flash('error', '稿件不存在。');
    redirect('/works.php');
}

function load_work_images(PDO $pdo, int $workId): array
{
    $imgStmt = $pdo->prepare('SELECT * FROM work_images WHERE work_id = ? ORDER BY sort ASC, id ASC');
    $imgStmt->execute([$workId]);
    return $imgStmt->fetchAll();
}

$images = load_work_images($pdo, $id);

$charStmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name ASC');
$charStmt->execute([(int) $user['id']]);
$characters = $charStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $error = '上传内容过大或被服务器截断，请减小图片后重试。';
    } else {
        verify_csrf();
        $action = (string) ($_POST['action'] ?? 'save');

        try {
            if ($action === 'delete_image') {
                $imageId = (int) ($_POST['image_id'] ?? 0);
                $del = $pdo->prepare('SELECT * FROM work_images WHERE id = ? AND work_id = ? LIMIT 1');
                $del->execute([$imageId, $id]);
                $row = $del->fetch();
                if ($row) {
                    delete_upload_file((string) $row['image_path']);
                    $pdo->prepare('DELETE FROM work_images WHERE id = ?')->execute([$imageId]);
                    if (($work['cover_image'] ?? '') === $row['image_path']) {
                        $next = $pdo->prepare('SELECT image_path FROM work_images WHERE work_id = ? ORDER BY sort ASC, id ASC LIMIT 1');
                        $next->execute([$id]);
                        $newCover = $next->fetchColumn() ?: null;
                        $pdo->prepare('UPDATE works SET cover_image = ? WHERE id = ?')->execute([$newCover, $id]);
                    }
                    flash('success', '已删除图片。');
                    redirect('/edit_work.php?id=' . $id);
                }
                throw new InvalidArgumentException('图片不存在。');
            }

            $characterId = (int) ($_POST['character_id'] ?? $work['character_id']);
            $categoryId = resolve_work_category_id((int) $user['id'], $_POST['category_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $date = trim((string) ($_POST['date'] ?? ''));

            if (!user_owns_character((int) $user['id'], $characterId)) {
                throw new InvalidArgumentException('请选择有效角色。');
            }
            if ($title === '') {
                throw new InvalidArgumentException('请填写稿件标题。');
            }
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException('日期格式不正确。');
            }

            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE works SET character_id = ?, category_id = ?, title = ?, description = ?, date = ? WHERE id = ?'
            )->execute([
                $characterId,
                $categoryId,
                $title,
                $description,
                $date !== '' ? $date : null,
                $id,
            ]);

            $editIds = $_POST['existing_ids'] ?? [];
            $editNames = $_POST['existing_names'] ?? [];
            $editDescs = $_POST['existing_descriptions'] ?? [];
            if (is_array($editIds)) {
                $upd = $pdo->prepare(
                    'UPDATE work_images SET image_name = ?, image_description = ? WHERE id = ? AND work_id = ?'
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
            if ($coverId > 0) {
                $cStmt = $pdo->prepare('SELECT image_path FROM work_images WHERE id = ? AND work_id = ? LIMIT 1');
                $cStmt->execute([$coverId, $id]);
                $coverPath = $cStmt->fetchColumn();
                if ($coverPath) {
                    $pdo->prepare('UPDATE works SET cover_image = ? WHERE id = ?')->execute([$coverPath, $id]);
                }
            }

            $ingest = ingest_uploaded_images((int) $user['id'], 'works/' . $id);
            append_work_image_paths($pdo, $id, $ingest['paths'], $_POST['image_names'] ?? [], $_POST['image_descriptions'] ?? []);

            $pdo->commit();

            if ($ingest['partial']) {
                flash(
                    'warning',
                    partial_upload_message(count($ingest['paths']), (int) $ingest['failed_from'], (string) $ingest['error'])
                );
                redirect('/edit_work.php?id=' . $id);
            }

            flash('success', '稿件信息已更新。');
            redirect('/work.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = user_error_message($e);
            $work['title'] = $_POST['title'] ?? $work['title'];
            $work['description'] = $_POST['description'] ?? $work['description'];
            $work['date'] = $_POST['date'] ?? $work['date'];
            $work['character_id'] = (int) ($_POST['character_id'] ?? $work['character_id']);
            $images = load_work_images($pdo, $id);
        }
    }
}

render_header('编辑稿件');
?>
<div class="container">
  <?php render_page_back('/work.php?id=' . $id, '返回稿件'); ?>
  <h1 class="page-title">编辑稿件</h1>
  <p class="page-lead">修改「<?= e($work['title'] ?? '') ?>」的信息与图片备注。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="wizard-panel active" style="display:block;max-width:760px;" id="upload-wizard" data-upload-progress data-cos-context="work_append" data-cos-work-id="<?= $id ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="save">

    <div class="form-grid">
      <div class="form-row">
        <label for="character_id">所属角色</label>
        <select id="character_id" name="character_id" required>
          <?php foreach ($characters as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $work['character_id'] === (int) $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <label for="title">稿件标题</label>
        <input id="title" name="title" type="text" required maxlength="255" value="<?= e($work['title'] ?? '') ?>">
      </div>
      <div class="form-row">
        <label for="date">日期</label>
        <input id="date" name="date" type="date" value="<?= e($work['date'] ?? '') ?>">
      </div>
      <?php render_category_select((int) $user['id'], (int) row_field($work, 'category_id', 0) ?: null); ?>
      <div class="form-row">
        <label for="description">稿件说明</label>
        <textarea id="description" name="description"><?= e($work['description'] ?? '') ?></textarea>
      </div>
    </div>

    <h2 class="section-title">已有图片</h2>
    <?php if (!$images): ?>
      <p class="hint">暂无图片，可在下方追加上传。</p>
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
                <textarea name="existing_descriptions[]" rows="2"><?= e($img['image_description'] ?? '') ?></textarea>
              </div>
              <label style="display:inline-flex;align-items:center;gap:0.4rem;font-weight:700;font-size:0.88rem;">
                <input type="radio" name="cover_image_id" value="<?= (int) $img['id'] ?>"
                  <?= (($work['cover_image'] ?? '') === $img['image_path']) ? 'checked' : '' ?>>
                设为封面
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="section-title">追加图片</h2>
    <div class="form-row">
      <label for="work-images">选择图片（可多选）</label>
      <input id="work-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
      <span class="hint">单张最大 <?= e(upload_max_bytes_human()) ?></span>
    </div>
    <div id="image-preview" class="preview-list"></div>
    <div id="remark-fields" class="preview-list"></div>
    <p class="hint">选择图片后会自动出现备注框；也可保存后再回来补备注。</p>

    <div class="btn-row">
      <button class="btn btn-primary" type="submit">保存</button>
      <a class="btn btn-ghost" href="/work.php?id=<?= $id ?>">返回</a>
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
          <div><strong><?= e($img['image_name'] ?: '未命名') ?></strong></div>
          <button class="btn btn-ghost btn-sm" type="submit">删除</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<script>
(() => {
  const input = document.getElementById("work-images");
  const remarks = document.getElementById("remark-fields");
  const preview = document.getElementById("image-preview");
  if (!input || !remarks) return;
  input.addEventListener("change", () => {
    remarks.innerHTML = "";
    if (preview) preview.innerHTML = "";
    [...input.files].forEach((file, i) => {
      const wrap = document.createElement("div");
      wrap.className = "preview-item";
      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      img.alt = file.name;
      const fields = document.createElement("div");
      fields.className = "form-grid";
      const base = file.name.replace(/\.[^.]+$/, "");
      fields.innerHTML = `
        <div class="form-row"><label>图片 ${i + 1} 名称</label>
          <input type="text" name="image_names[]" value="${base.replace(/"/g, "&quot;")}"></div>
        <div class="form-row"><label>备注</label>
          <textarea name="image_descriptions[]" rows="2"></textarea></div>`;
      wrap.append(img, fields);
      remarks.appendChild(wrap);
    });
  });
})();
</script>
<?php render_footer(); ?>
