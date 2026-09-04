<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', '角色不存在。');
    redirect('/characters.php');
}

$stmt = $pdo->prepare(
    'SELECT id, user_id, name, description, tags, cover_image, avatar_image, create_time, update_time
     FROM characters WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$id, (int) $user['id']]);
$character = $stmt->fetch();
if (!$character) {
    flash('error', '角色不存在或无权访问。');
    redirect('/characters.php');
}

$imgStmt = $pdo->prepare(
    'SELECT * FROM character_images WHERE character_id = ? ORDER BY sort ASC, id ASC'
);
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll();

$filterCat = (int) ($_GET['cat'] ?? 0);
$userCategories = get_user_categories((int) $user['id']);

$workSql = 'SELECT w.id, w.character_id, w.category_id, w.title, w.description, w.date, w.cover_image, w.create_time,
                   wc.name AS category_name,
                   (SELECT COUNT(*) FROM work_images wi WHERE wi.work_id = w.id) AS image_count
            FROM works w
            LEFT JOIN work_categories wc ON wc.id = w.category_id
            WHERE w.character_id = ?';
$workParams = [$id];
if ($filterCat > 0) {
    $workSql .= ' AND w.category_id = ?';
    $workParams[] = $filterCat;
} elseif ($filterCat === -1) {
    $workSql .= ' AND w.category_id IS NULL';
}
$workSql .= ' ORDER BY COALESCE(w.date, w.create_time) DESC, w.id DESC';
$workStmt = $pdo->prepare($workSql);
$workStmt->execute($workParams);
$works = $workStmt->fetchAll();

$tags = parse_tags(row_str($character, 'tags') ?: null);
$shareUrl = absolute_url('share_character.php?id=' . $id);
$charName = display_character_name($character);

render_header($charName);
?>
<div class="container">
  <?php render_page_back('/characters.php', '我的角色'); ?>
  <section class="char-hero">
    <div class="char-avatar">
      <img src="<?= e(image_url(character_avatar_path($character))) ?>" alt="<?= e($charName) ?>">
    </div>
    <div class="char-meta">
      <h1><?= e($charName) ?></h1>
      <?php if (row_str($character, 'description') !== ''): ?>
        <p class="desc"><?= e(row_str($character, 'description')) ?></p>
      <?php endif; ?>
      <?php if ($tags): ?>
        <div class="tag-list">
          <?php foreach ($tags as $tag): ?>
            <span class="tag"><?= e($tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="share-bar">
        <a class="btn btn-primary btn-sm" href="/upload_work.php?character_id=<?= $id ?>">上传稿件</a>
        <a class="btn btn-primary btn-sm" href="/invites.php?character_id=<?= $id ?>">画师上传链接</a>
        <a class="btn btn-ghost btn-sm" href="/edit_character.php?id=<?= $id ?>">编辑信息</a>
        <a class="btn btn-ghost btn-sm" href="/share_character.php?id=<?= $id ?>" target="_blank">分享页</a>
        <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($shareUrl) ?>">复制分享链接</button>
      </div>
    </div>
  </section>

  <h2 class="section-title">主设展示 <span><?= count($images) ?> 张</span></h2>
  <?php if (!$images): ?>
    <div class="empty-state"><p>尚未上传主设图片。</p></div>
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

  <?php if ($userCategories): ?>
    <div class="cat-filter">
      <a class="cat-chip<?= $filterCat === 0 ? ' active' : '' ?>" href="/character.php?id=<?= $id ?>">全部</a>
      <?php foreach ($userCategories as $cat): ?>
        <a class="cat-chip<?= $filterCat === (int) $cat['id'] ? ' active' : '' ?>"
           href="/character.php?id=<?= $id ?>&cat=<?= (int) $cat['id'] ?>"><?= e(row_str($cat, 'name')) ?></a>
      <?php endforeach; ?>
      <a class="cat-chip<?= $filterCat === -1 ? ' active' : '' ?>" href="/character.php?id=<?= $id ?>&cat=-1">未分类</a>
      <a class="cat-chip cat-chip-muted" href="/categories.php">管理分类</a>
    </div>
  <?php endif; ?>

  <?php if (!$works): ?>
    <div class="empty-state">
      <p>还没有稿件。</p>
      <p><a href="/upload_work.php?character_id=<?= $id ?>">上传第一件稿件</a></p>
    </div>
  <?php else: ?>
    <div class="work-list">
      <?php foreach ($works as $w): ?>
        <a class="work-item" href="/work.php?id=<?= (int) $w['id'] ?>">
          <div class="work-thumb">
            <img src="<?= e(image_url($w['cover_image'])) ?>" alt="<?= e($w['title']) ?>" loading="lazy">
          </div>
          <div class="work-info">
            <h3><?= e($w['title']) ?></h3>
            <div class="work-meta">
              <?php if (!empty($w['category_name'])): ?>
                <span class="tag"><?= e($w['category_name']) ?></span>
              <?php endif; ?>
              <span><?= e(format_date($w['date'])) ?></span>
            </div>
            <?php if ($w['description']): ?>
              <p class="work-excerpt"><?= e(mb_substr((string) $w['description'], 0, 60)) ?></p>
            <?php endif; ?>
          </div>
          <div class="work-stat"><?= (int) $w['image_count'] ?> 张</div>
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
