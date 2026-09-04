<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$error = '';
$createdUrl = '';
$editInvite = null;

$charId = (int) ($_GET['character_id'] ?? $_POST['character_id'] ?? 0);
$filter = (string) ($_GET['filter'] ?? $_POST['filter'] ?? 'all');
if (!in_array($filter, ['all', 'active', 'inactive'], true)) {
    $filter = 'all';
}

$charStmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name ASC');
$charStmt->execute([(int) $user['id']]);
$characters = $charStmt->fetchAll();

if (!$characters) {
    flash('info', '请先创建角色，再生成画师上传链接。');
    redirect('/create_character.php');
}

if ($charId > 0 && !user_owns_character((int) $user['id'], $charId)) {
    $charId = 0;
}

function invites_list_query(int $charId, string $filter): string
{
    $q = [];
    if ($charId > 0) {
        $q['character_id'] = (string) $charId;
    }
    if ($filter !== 'all') {
        $q['filter'] = $filter;
    }
    return $q ? ('?' . http_build_query($q)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'create');

    try {
        if ($action === 'toggle') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id, is_active FROM upload_invites WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$inviteId, (int) $user['id']]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new InvalidArgumentException('邀请不存在。');
            }
            $new = (int) $row['is_active'] ? 0 : 1;
            $pdo->prepare('UPDATE upload_invites SET is_active = ? WHERE id = ?')->execute([$new, $inviteId]);
            flash('success', $new ? '链接已重新开启。' : '链接已关闭。');
            redirect('/invites.php' . invites_list_query($charId, $filter));
        }

        if ($action === 'delete') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            $pdo->prepare('DELETE FROM upload_invites WHERE id = ? AND user_id = ?')->execute([$inviteId, (int) $user['id']]);
            flash('success', '邀请链接已删除。');
            redirect('/invites.php' . invites_list_query($charId, $filter));
        }

        if ($action === 'update') {
            $inviteId = (int) ($_POST['invite_id'] ?? 0);
            update_upload_invite((int) $user['id'], $inviteId, [
                'note'         => $_POST['note'] ?? '',
                'artist_hint'  => $_POST['artist_hint'] ?? '',
                'max_uses'     => parse_invite_uses_preset((string) ($_POST['uses_preset'] ?? '1')),
                'expires_days' => parse_invite_expires_preset((string) ($_POST['expires_preset'] ?? '3')),
            ]);
            flash('success', '链接设置已更新（有效期从当前时间重新计算）。');
            redirect('/invites.php' . invites_list_query($charId, $filter));
        }

        $characterId = (int) ($_POST['character_id'] ?? 0);
        $result = create_upload_invite((int) $user['id'], $characterId, [
            'note'         => $_POST['note'] ?? '',
            'artist_hint'  => $_POST['artist_hint'] ?? '',
            'max_uses'     => parse_invite_uses_preset((string) ($_POST['uses_preset'] ?? '1')),
            'expires_days' => parse_invite_expires_preset((string) ($_POST['expires_preset'] ?? '3')),
        ]);
        $createdUrl = $result['url'];
        $charId = $characterId;
        flash('success', '画师上传链接已生成，请复制发给画师。');
    } catch (Throwable $e) {
        $error = user_error_message($e);
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare(
        'SELECT i.*, c.name AS character_name
         FROM upload_invites i
         INNER JOIN characters c ON c.id = i.character_id
         WHERE i.id = ? AND i.user_id = ? LIMIT 1'
    );
    $editStmt->execute([$editId, (int) $user['id']]);
    $editInvite = $editStmt->fetch() ?: null;
}

$listSql = 'SELECT i.*, c.name AS character_name
            FROM upload_invites i
            INNER JOIN characters c ON c.id = i.character_id
            WHERE i.user_id = ?';
$params = [(int) $user['id']];
if ($charId > 0) {
    $listSql .= ' AND i.character_id = ?';
    $params[] = $charId;
}
$listSql .= ' ORDER BY i.create_time DESC';
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$allInvites = $listStmt->fetchAll();

$invites = array_values(array_filter($allInvites, static function (array $inv) use ($filter): bool {
    if ($filter === 'active') {
        return invite_is_usable($inv);
    }
    if ($filter === 'inactive') {
        return !invite_is_usable($inv);
    }
    return true;
}));

render_header('画师上传链接');
?>
<div class="container">
  <h1 class="page-title">画师上传链接</h1>
  <p class="page-lead">生成专属链接发给画师。可设置 <strong>1 / 3 / 5 / 永久</strong> 次数，以及 <strong>1 / 3 / 5 / 永久</strong> 有效时间。</p>

  <?php if ($error): ?>
    <div class="flash flash-error"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($createdUrl): ?>
    <div class="flash flash-success invite-created-bar">
      <span class="invite-created-url"><?= e($createdUrl) ?></span>
      <button type="button" class="btn btn-primary btn-sm" data-copy="<?= e($createdUrl) ?>">复制链接</button>
    </div>
  <?php endif; ?>

  <?php if ($editInvite): ?>
    <?php
      $usesPreset = invite_guess_uses_preset(
          $editInvite['max_uses'] !== null && $editInvite['max_uses'] !== '' ? (int) $editInvite['max_uses'] : null
      );
      $expiresPreset = invite_guess_expires_preset(
          $editInvite['expires_at'] ?? null,
          $editInvite['create_time'] ?? null
      );
      $artistHint = (string) ($editInvite['artist_hint'] ?? '');
      $note = (string) ($editInvite['note'] ?? '');
    ?>
    <form method="post" class="wizard-panel active invite-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="invite_id" value="<?= (int) $editInvite['id'] ?>">
      <input type="hidden" name="character_id" value="<?= $charId ?>">
      <input type="hidden" name="filter" value="<?= e($filter) ?>">
      <h2 class="section-title" style="margin-top:0;">编辑链接 · <?= e($editInvite['character_name']) ?></h2>
      <?php require __DIR__ . '/includes/invite_form_fields.php'; ?>
      <div class="btn-row">
        <button class="btn btn-primary" type="submit">保存修改</button>
        <a class="btn btn-ghost" href="/invites.php<?= e(invites_list_query($charId, $filter)) ?>">取消</a>
      </div>
    </form>
  <?php else: ?>
    <form method="post" class="wizard-panel active invite-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <h2 class="section-title" style="margin-top:0;">生成新链接</h2>
      <div class="form-grid">
        <div class="form-row">
          <label for="character_id">指定角色</label>
          <select id="character_id" name="character_id" required>
            <?php foreach ($characters as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $charId === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php
        $usesPreset = '1';
        $expiresPreset = '3';
        $artistHint = '';
        $note = '';
        require __DIR__ . '/includes/invite_form_fields.php';
      ?>
      <div class="btn-row">
        <button class="btn btn-primary" type="submit">生成链接</button>
        <?php if ($charId): ?>
          <a class="btn btn-ghost" href="/character.php?id=<?= $charId ?>">返回角色</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <div class="invite-list-head">
    <h2 class="section-title">链接管理 <span><?= count($invites) ?></span></h2>
    <div class="invite-list-tools">
      <?php if (count($characters) > 1): ?>
        <form method="get" class="invite-char-filter">
          <?php if ($filter !== 'all'): ?>
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
          <?php endif; ?>
          <select name="character_id" onchange="this.form.submit()">
            <option value="0"<?= $charId === 0 ? ' selected' : '' ?>>全部角色</option>
            <?php foreach ($characters as $c): ?>
              <option value="<?= (int) $c['id'] ?>"<?= $charId === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <div class="cat-filter invite-filter">
      <a class="cat-chip<?= $filter === 'all' ? ' active' : '' ?>" href="/invites.php<?= e(invites_list_query($charId, 'all')) ?>">全部</a>
      <a class="cat-chip<?= $filter === 'active' ? ' active' : '' ?>" href="/invites.php<?= e(invites_list_query($charId, 'active')) ?>">可用</a>
      <a class="cat-chip<?= $filter === 'inactive' ? ' active' : '' ?>" href="/invites.php<?= e(invites_list_query($charId, 'inactive')) ?>">已失效</a>
      </div>
    </div>
  </div>

  <?php if (!$invites): ?>
    <div class="empty-state"><p><?= $filter === 'all' ? '还没有邀请链接。' : '没有符合筛选的链接。' ?></p></div>
  <?php else: ?>
    <div class="invite-list">
      <?php foreach ($invites as $inv): ?>
        <?php
          $url = absolute_url('artist_upload.php?token=' . $inv['token']);
          $badge = invite_status_badge($inv);
          $maxUses = $inv['max_uses'] !== null && $inv['max_uses'] !== '' ? (int) $inv['max_uses'] : null;
        ?>
        <article class="invite-card">
          <div class="invite-card-head">
            <div>
              <h3><?= e($inv['character_name']) ?></h3>
              <?php if ($inv['artist_hint']): ?>
                <p class="invite-hint"><?= e($inv['artist_hint']) ?></p>
              <?php endif; ?>
            </div>
            <span class="invite-badge <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span>
          </div>

          <div class="invite-meta-grid">
            <div>
              <span class="invite-meta-label">可用次数</span>
              <strong><?= e(invite_max_uses_label($maxUses)) ?></strong>
              <span class="invite-meta-sub">已用 <?= (int) $inv['used_count'] ?><?= $maxUses !== null ? ' / ' . $maxUses : '' ?></span>
            </div>
            <div>
              <span class="invite-meta-label">有效期</span>
              <strong><?= e(invite_expires_label($inv['expires_at'] ?? null)) ?></strong>
              <span class="invite-meta-sub">创建于 <?= e(substr((string) $inv['create_time'], 0, 16)) ?></span>
            </div>
          </div>

          <?php if ($inv['note']): ?>
            <p class="invite-note"><?= e($inv['note']) ?></p>
          <?php endif; ?>

          <p class="invite-url"><?= e($url) ?></p>

          <div class="share-bar invite-actions">
            <button type="button" class="btn btn-primary btn-sm" data-copy="<?= e($url) ?>">复制</button>
            <a class="btn btn-ghost btn-sm" href="<?= e($url) ?>" target="_blank" rel="noopener">打开</a>
            <a class="btn btn-ghost btn-sm" href="/invites.php?edit=<?= (int) $inv['id'] ?><?= $charId ? '&character_id=' . $charId : '' ?>&filter=<?= e(urlencode($filter)) ?>">编辑</a>
            <form method="post" class="invite-inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="invite_id" value="<?= (int) $inv['id'] ?>">
              <input type="hidden" name="character_id" value="<?= $charId ?>">
              <input type="hidden" name="filter" value="<?= e($filter) ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $inv['is_active'] ? '关闭' : '开启' ?></button>
            </form>
            <form method="post" class="invite-inline-form" onsubmit="return confirm('确定删除此链接？');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="invite_id" value="<?= (int) $inv['id'] ?>">
              <input type="hidden" name="character_id" value="<?= $charId ?>">
              <input type="hidden" name="filter" value="<?= e($filter) ?>">
              <button class="btn btn-ghost btn-sm" type="submit">删除</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
