<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('稿件不存在。');
}

$stmt = $pdo->prepare(
    'SELECT w.*, c.name AS character_name, c.id AS character_id,
            u.display_name, u.username, wc.name AS category_name
     FROM works w
     INNER JOIN characters c ON c.id = w.character_id
     INNER JOIN users u ON u.id = c.user_id
     LEFT JOIN work_categories wc ON wc.id = w.category_id
     WHERE w.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$work = $stmt->fetch();
if (!$work) {
    http_response_code(404);
    exit('稿件不存在。');
}

$imgStmt = $pdo->prepare(
    'SELECT * FROM work_images WHERE work_id = ? ORDER BY sort ASC, id ASC'
);
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();
$owner = $work['display_name'] ?: $work['username'];
$workTitle = display_work_title($work);

render_header($workTitle . '（分享）', ['body_class' => 'page-share']);
?>
<div class="container">
  <span class="share-badge"><?= e(site('site.share_work_badge', '公开分享 · 单个稿件')) ?></span>

  <div class="work-header">
    <p style="margin:0 0 0.35rem;">
      <a href="/share_character.php?id=<?= (int) $work['character_id'] ?>"><?= e($work['character_name'] ?? '') ?></a>
      <span style="color:var(--ink-muted);"> · by <?= e($owner) ?></span>
    </p>
    <h1 class="page-title"><?= e($workTitle) ?></h1>
    <p class="meta">
      <?php if (!empty($work['category_name'])): ?>
        <span class="tag"><?= e($work['category_name']) ?></span> ·
      <?php endif; ?>
      <?= e(format_date($work['date'])) ?> · <?= count($images) ?> 张图片
    </p>
    <?php if ($work['description']): ?>
      <p class="desc"><?= e($work['description']) ?></p>
    <?php endif; ?>
  </div>

  <?php if (!$images): ?>
    <div class="empty-state"><p>暂无图片。</p></div>
  <?php else: ?>
    <div class="image-masonry">
      <?php foreach ($images as $img): ?>
        <article class="image-card">
          <figure>
            <a class="thumb" href="<?= e(image_url($img['image_path'])) ?>" data-lightbox>
              <img src="<?= e(image_url($img['image_path'])) ?>" alt="<?= e($img['image_name'] ?: '稿件图') ?>" loading="lazy">
            </a>
            <figcaption>
              <p class="img-name"><?= e($img['image_name'] ?: basename((string) $img['image_path'])) ?></p>
              <?php if ($img['image_description']): ?>
                <p class="img-desc">备注：<?= e($img['image_description']) ?></p>
              <?php endif; ?>
            </figcaption>
          </figure>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
  <button type="button" class="lightbox-close" aria-label="关闭">×</button>
  <img id="lightbox-img" alt="预览">
</div>
<?php render_footer(); ?>
