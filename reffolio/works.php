<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$filterCat = (int) ($_GET['cat'] ?? 0);
$userCategories = get_user_categories((int) $user['id']);

$sql = 'SELECT w.*, c.name AS character_name, wc.name AS category_name,
               (SELECT COUNT(*) FROM work_images wi WHERE wi.work_id = w.id) AS image_count
        FROM works w
        INNER JOIN characters c ON c.id = w.character_id
        LEFT JOIN work_categories wc ON wc.id = w.category_id
        WHERE c.user_id = ?';
$params = [(int) $user['id']];
if ($filterCat > 0) {
    $sql .= ' AND w.category_id = ?';
    $params[] = $filterCat;
} elseif ($filterCat === -1) {
    $sql .= ' AND w.category_id IS NULL';
}
$sql .= ' ORDER BY COALESCE(w.date, w.create_time) DESC, w.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$works = $stmt->fetchAll();

render_header('全部稿件');
?>
<div class="container">
  <div class="page-head-row">
    <div class="page-head-main">
      <h1 class="page-title">全部稿件</h1>
      <p class="page-lead page-lead-tight">共 <?= count($works) ?> 件作品</p>
    </div>
    <div class="page-head-actions btn-row">
      <a class="btn btn-ghost btn-sm" href="/categories.php">分类管理</a>
      <a class="btn btn-primary btn-sm" href="/upload_work.php">上传稿件</a>
    </div>
  </div>

  <?php if ($userCategories): ?>
    <div class="cat-filter">
      <a class="cat-chip<?= $filterCat === 0 ? ' active' : '' ?>" href="/works.php">全部</a>
      <?php foreach ($userCategories as $cat): ?>
        <a class="cat-chip<?= $filterCat === (int) $cat['id'] ? ' active' : '' ?>"
           href="/works.php?cat=<?= (int) $cat['id'] ?>"><?= e(row_str($cat, 'name')) ?></a>
      <?php endforeach; ?>
      <a class="cat-chip<?= $filterCat === -1 ? ' active' : '' ?>" href="/works.php?cat=-1">未分类</a>
    </div>
  <?php endif; ?>

  <?php if (!$works): ?>
    <div class="empty-state">
      <p>还没有稿件。</p>
      <p><a href="/upload_work.php">去上传</a></p>
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
              <span><?= e($w['character_name']) ?></span>
              <span class="work-meta-sep">·</span>
              <span><?= e(format_date($w['date'])) ?></span>
              <?php if (!empty($w['category_name'])): ?>
                <span class="tag"><?= e($w['category_name']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="work-stat"><?= (int) $w['image_count'] ?> 张</div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
