<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();

$stmt = $pdo->prepare(
    'SELECT c.*,
            (SELECT COUNT(*) FROM works w WHERE w.character_id = c.id) AS work_count,
            (SELECT COUNT(*) FROM character_images ci WHERE ci.character_id = c.id) AS image_count
     FROM characters c
     WHERE c.user_id = ?
     ORDER BY c.update_time DESC, c.id DESC'
);
$stmt->execute([(int) $user['id']]);
$characters = $stmt->fetchAll();

render_header('我的角色');
?>
<div class="container">
  <div class="page-head-row" style="display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div>
      <h1 class="page-title">我的角色</h1>
      <p class="page-lead" style="margin-bottom:0;">共 <?= count($characters) ?> 个角色设定</p>
    </div>
    <a class="btn btn-primary" href="/create_character.php">新建角色</a>
  </div>

  <?php if (!$characters): ?>
    <div class="empty-state">
      <p>还没有角色设定。</p>
      <p><a href="/create_character.php">创建第一个角色</a></p>
    </div>
  <?php else: ?>
    <div class="char-grid">
      <?php foreach ($characters as $c): ?>
        <a class="char-tile" href="/character.php?id=<?= (int) $c['id'] ?>">
          <div class="char-tile-cover">
            <img src="<?= e(image_url(row_str($c, 'cover_image') ?: null)) ?>" alt="<?= e(display_character_name($c)) ?>" loading="lazy">
          </div>
          <div class="char-tile-body">
            <h3><?= e(display_character_name($c)) ?></h3>
            <p><?= e(mb_substr(row_str($c, 'description'), 0, 80)) ?></p>
            <div class="tag-list">
              <?php foreach (array_slice(parse_tags(row_str($c, 'tags') ?: null), 0, 4) as $tag): ?>
                <span class="tag"><?= e($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <p style="margin-top:0.65rem;font-size:0.82rem;color:var(--ink-muted);">
              主设 <?= (int) $c['image_count'] ?> · 稿件 <?= (int) $c['work_count'] ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
