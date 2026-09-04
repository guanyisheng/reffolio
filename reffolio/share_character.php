<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('角色不存在。');
}

$stmt = $pdo->prepare('SELECT c.*, u.display_name, u.username
                       FROM characters c
                       INNER JOIN users u ON u.id = c.user_id
                       WHERE c.id = ? LIMIT 1');
$stmt->execute([$id]);
$character = $stmt->fetch();
if (!$character) {
    http_response_code(404);
    exit('角色不存在。');
}

$imgStmt = $pdo->prepare(
    'SELECT * FROM character_images WHERE character_id = ? ORDER BY sort ASC, id ASC'
);
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$workStmt = $pdo->prepare(
    'SELECT w.*, wc.name AS category_name,
            (SELECT COUNT(*) FROM work_images wi WHERE wi.work_id = w.id) AS image_count
     FROM works w
     LEFT JOIN work_categories wc ON wc.id = w.category_id
     WHERE w.character_id = ?
     ORDER BY COALESCE(w.date, w.create_time) DESC, w.id DESC'
);
$workStmt->execute([$id]);
$works = $workStmt->fetchAll();

$tags = parse_tags($character['tags'] ?? null);
$owner = $character['display_name'] ?: $character['username'];
$charName = display_character_name($character);

render_header($charName . '（分享）', ['body_class' => 'page-share']);
?>
<div class="container">
  <span class="share-badge"><?= e(site('site.share_char_badge', '公开分享 · 角色设定')) ?></span>

  <section class="char-hero">
    <div class="char-avatar">
      <img src="<?= e(image_url(character_avatar_path($character))) ?>" alt="<?= e($charName) ?>">
    </div>
    <div class="char-meta">
      <h1><?= e($charName) ?></h1>
      <p class="desc" style="margin-bottom:0.5rem;color:var(--ink-muted);font-size:0.9rem;">by <?= e($owner) ?></p>
      <?php if ($character['description']): ?>
        <p class="desc"><?= e($character['description']) ?></p>
      <?php endif; ?>
      <?php if ($tags): ?>
        <div class="tag-list">
          <?php foreach ($tags as $tag): ?>
            <span class="tag"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <h2 class="section-title">角色设定 <span><?= count($images) ?> 张</span></h2>
  <?php if (!$images): ?>
    <div class="empty-state"><p>暂无主设图片。</p></div>
  <?php else: ?>
    <div class="image-masonry">
      <?php foreach ($images as $img): ?>
        <article class="image-card">
          <figure>
            <a class="thumb" href="<?= e(image_url($img['image_path'])) ?>" data-lightbox>
              <img src="<?= e(image_url($img['image_path'])) ?>" alt="<?= e($img['image_name'] ?: '主设') ?>" loading="lazy">
            </a>
            <figcaption>
              <p class="img-name"><?= e($img['image_name'] ?: '未命名') ?></p>
              <?php if ($img['description']): ?>
                <p class="img-desc"><?= e($img['description']) ?></p>
              <?php endif; ?>
            </figcaption>
          </figure>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h2 class="section-title">全部稿件 <span><?= count($works) ?> 件</span></h2>
  <?php if (!$works): ?>
    <div class="empty-state"><p>暂无稿件。</p></div>
  <?php else: ?>
    <div class="work-list">
      <?php foreach ($works as $w): ?>
        <a class="work-item" href="/share_work.php?id=<?= (int) $w['id'] ?>">
          <div class="work-thumb">
            <img src="<?= e(image_url($w['cover_image'])) ?>" alt="<?= e($w['title']) ?>" loading="lazy">
          </div>
          <div class="work-info">
            <h3><?= e($w['title']) ?></h3>
            <p>
              <?php if (!empty($w['category_name'])): ?>
                <span class="tag" style="margin-right:0.25rem;"><?= e($w['category_name']) ?></span>
              <?php endif; ?>
              <?= e(format_date($w['date'])) ?>
            </p>
          </div>
          <div class="work-stat"><?= (int) $w['image_count'] ?> 张图片</div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div id="lightbox" class="lightbox" role="dialog" aria-modal="true">
  <button type="button" class="lightbox-close" aria-label="关闭">×</button>
  <img id="lightbox-img" alt="预览">
</div>
<?php render_footer(); ?>
