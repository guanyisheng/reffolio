<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', '稿件不存在。');
    redirect('/works.php');
}

$stmt = $pdo->prepare(
    'SELECT w.*, c.name AS character_name, c.id AS character_id, c.user_id,
            wc.name AS category_name
     FROM works w
     INNER JOIN characters c ON c.id = w.character_id
     LEFT JOIN work_categories wc ON wc.id = w.category_id
     WHERE w.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$work = $stmt->fetch();

if (!$work || (int) $work['user_id'] !== (int) $user['id']) {
    flash('error', '稿件不存在或无权访问。');
    redirect('/works.php');
}

$imgStmt = $pdo->prepare(
    'SELECT * FROM work_images WHERE work_id = ? ORDER BY sort ASC, id ASC'
);
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$shareUrl = absolute_url('share_work.php?id=' . $id);
$workTitle = display_work_title($work);

render_header($workTitle);
?>
<div class="container page-work">
  <?php render_page_back('/character.php?id=' . (int) $work['character_id'], '返回角色'); ?>
  <header class="work-header work-header-card">
    <p class="work-breadcrumb">
      <a href="/character.php?id=<?= (int) $work['character_id'] ?>"><?= e($work['character_name'] ?? '') ?></a>
    </p>
    <h1 class="page-title"><?= e($workTitle) ?></h1>
    <div class="work-meta">
      <?php if (!empty($work['category_name'])): ?>
        <span class="tag"><?= e($work['category_name']) ?></span>
      <?php endif; ?>
      <span><?= e(format_date($work['date'])) ?></span>
      <span><?= count($images) ?> 张图片</span>
    </div>
    <?php if ($work['description']): ?>
      <p class="desc work-desc"><?= e($work['description']) ?></p>
    <?php endif; ?>
    <div class="share-bar share-bar-work">
      <a class="btn btn-primary btn-sm" href="/edit_work.php?id=<?= $id ?>">编辑信息</a>
      <a class="btn btn-ghost btn-sm" href="/share_work.php?id=<?= $id ?>" target="_blank">分享页</a>
      <button type="button" class="btn btn-ghost btn-sm share-bar-wide" data-copy="<?= e($shareUrl) ?>">复制分享链接</button>
    </div>
  </header>

  <?php if (!$images): ?>
    <div class="empty-state"><p>该稿件暂无图片。</p></div>
  <?php else: ?>
    <form method="post" action="/download_images.php" class="work-download-form">
      <?= csrf_field() ?>
      <input type="hidden" name="work_id" value="<?= $id ?>">

      <div class="toolbar">
        <label class="toolbar-check">
          <input type="checkbox" id="select-all">
          <span>全选</span>
        </label>
        <button class="btn btn-primary btn-sm" type="submit">批量下载原图（ZIP）</button>
        <span class="toolbar-hint">勾选图片后打包下载</span>
      </div>

      <div class="image-masonry image-masonry-work">
        <?php foreach ($images as $img): ?>
          <article class="image-card">
            <figure>
              <a class="thumb" href="<?= e(image_url($img['image_path'])) ?>" data-lightbox>
                <img src="<?= e(image_url($img['image_path'])) ?>" alt="<?= e($img['image_name'] ?: '稿件图') ?>" loading="lazy">
              </a>
              <figcaption>
                <p class="img-name"><?= e($img['image_name'] ?: basename((string) $img['image_path'])) ?></p>
                <?php if ($img['image_description']): ?>
                  <p class="img-desc"><?= e($img['image_description']) ?></p>
                <?php endif; ?>
              </figcaption>
            </figure>
            <label class="check-row">
              <input type="checkbox" name="image_ids[]" value="<?= (int) $img['id'] ?>">
              <span>选择下载</span>
            </label>
          </article>
        <?php endforeach; ?>
      </div>
    </form>
  <?php endif; ?>
</div>

<div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
  <button type="button" class="lightbox-close" aria-label="关闭">×</button>
  <img id="lightbox-img" alt="预览">
</div>
<?php render_footer(); ?>
