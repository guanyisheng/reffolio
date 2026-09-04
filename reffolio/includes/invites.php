<?php
/**
 * 画师上传邀请相关
 */
declare(strict_types=1);

function find_invite_by_token(string $token): ?array
{
    global $pdo;
    $token = trim($token);
    if ($token === '' || strlen($token) < 16) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT i.*, c.name AS character_name, c.cover_image, c.avatar_image, c.description AS character_description,
                u.display_name AS owner_name, u.username AS owner_username
         FROM upload_invites i
         INNER JOIN characters c ON c.id = i.character_id
         INNER JOIN users u ON u.id = i.user_id
         WHERE i.token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invite_is_usable(array $invite): bool
{
    if (!(int) ($invite['is_active'] ?? 0)) {
        return false;
    }
    if (!empty($invite['expires_at']) && strtotime((string) $invite['expires_at']) < time()) {
        return false;
    }
    $max = $invite['max_uses'];
    if ($max !== null && $max !== '' && (int) $invite['used_count'] >= (int) $max) {
        return false;
    }
    return true;
}

function invite_unusable_reason(array $invite): string
{
    if (!(int) ($invite['is_active'] ?? 0)) {
        return '此上传链接已被关闭。';
    }
    if (!empty($invite['expires_at']) && strtotime((string) $invite['expires_at']) < time()) {
        return '此上传链接已过期。';
    }
    $max = $invite['max_uses'];
    if ($max !== null && $max !== '' && (int) $invite['used_count'] >= (int) $max) {
        return '此上传链接已达到使用次数上限。';
    }
    return '此上传链接不可用。';
}

function invite_max_uses_label(?int $maxUses): string
{
    if ($maxUses === null) {
        return '永久（不限次数）';
    }
    return (string) $maxUses . ' 次';
}

function invite_expires_label(?string $expiresAt): string
{
    if ($expiresAt === null || trim($expiresAt) === '') {
        return '永久有效';
    }
    $ts = strtotime($expiresAt);
    if ($ts === false) {
        return (string) $expiresAt;
    }
    if ($ts < time()) {
        return '已过期（' . date('Y-m-d H:i', $ts) . '）';
    }
    return date('Y-m-d H:i', $ts) . ' 到期';
}

function invite_status_badge(array $invite): array
{
    if (!(int) ($invite['is_active'] ?? 0)) {
        return ['label' => '已关闭', 'class' => 'invite-badge-off'];
    }
    if (!invite_is_usable($invite)) {
        return ['label' => '已失效', 'class' => 'invite-badge-expired'];
    }
    return ['label' => '可用', 'class' => 'invite-badge-ok'];
}

/** @return int|null max uses; null = unlimited */
function parse_invite_uses_preset(string $preset): ?int
{
    return match ($preset) {
        '1' => 1,
        '3' => 3,
        '5' => 5,
        'unlimited' => null,
        default => 1,
    };
}

/** @return int days; 0 = never expires */
function parse_invite_expires_preset(string $preset): int
{
    return match ($preset) {
        '1' => 1,
        '3' => 3,
        '5' => 5,
        'never' => 0,
        default => 3,
    };
}

function invite_preset_checked(string $current, string $value): string
{
    return $current === $value ? ' checked' : '';
}

function invite_guess_uses_preset(?int $maxUses): string
{
    if ($maxUses === null) {
        return 'unlimited';
    }
    return match ($maxUses) {
        1, 3, 5 => (string) $maxUses,
        default => '1',
    };
}

function invite_guess_expires_preset(?string $expiresAt, ?string $createTime = null): string
{
    if ($expiresAt === null || trim((string) $expiresAt) === '') {
        return 'never';
    }
    $end = strtotime((string) $expiresAt);
    if ($end === false) {
        return '3';
    }
    $start = $createTime ? strtotime((string) $createTime) : time();
    if ($start === false) {
        $start = time();
    }
    $days = (int) round(($end - $start) / 86400);
    return match ($days) {
        1 => '1',
        3 => '3',
        5 => '5',
        default => '3',
    };
}

function update_upload_invite(int $userId, int $inviteId, array $opts): void
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM upload_invites WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$inviteId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new InvalidArgumentException('邀请不存在。');
    }

    $note = array_key_exists('note', $opts) ? trim((string) $opts['note']) : (string) ($row['note'] ?? '');
    $artistHint = array_key_exists('artist_hint', $opts) ? trim((string) $opts['artist_hint']) : (string) ($row['artist_hint'] ?? '');

    $maxUses = $row['max_uses'];
    if (array_key_exists('max_uses', $opts)) {
        $maxUses = $opts['max_uses'];
        if ($maxUses !== null && $maxUses !== '') {
            $maxUses = max(1, (int) $maxUses);
        } else {
            $maxUses = null;
        }
    }

    $expiresAt = $row['expires_at'];
    if (array_key_exists('expires_days', $opts)) {
        $days = (int) $opts['expires_days'];
        $expiresAt = $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
    }

    $pdo->prepare(
        'UPDATE upload_invites
         SET note = ?, artist_hint = ?, max_uses = ?, expires_at = ?
         WHERE id = ? AND user_id = ?'
    )->execute([
        $note !== '' ? $note : null,
        $artistHint !== '' ? $artistHint : null,
        $maxUses,
        $expiresAt,
        $inviteId,
        $userId,
    ]);
}

function create_upload_invite(int $userId, int $characterId, array $opts = []): array
{
    global $pdo;
    if (!user_owns_character($userId, $characterId)) {
        throw new InvalidArgumentException('无权为该角色创建邀请。');
    }

    $token = bin2hex(random_bytes(24));
    $note = trim((string) ($opts['note'] ?? ''));
    $artistHint = trim((string) ($opts['artist_hint'] ?? ''));
    $maxUses = $opts['max_uses'] ?? 1;
    $expiresDays = (int) ($opts['expires_days'] ?? 14);

    $maxUsesVal = null;
    if ($maxUses !== '' && $maxUses !== null) {
        $maxUsesVal = max(1, (int) $maxUses);
    }

    $expiresAt = null;
    if ($expiresDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresDays * 86400);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO upload_invites
         (user_id, character_id, token, note, artist_hint, max_uses, expires_at, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([
        $userId,
        $characterId,
        $token,
        $note !== '' ? $note : null,
        $artistHint !== '' ? $artistHint : null,
        $maxUsesVal,
        $expiresAt,
    ]);

    return [
        'id'    => (int) $pdo->lastInsertId(),
        'token' => $token,
        'url'   => absolute_url('artist_upload.php?token=' . $token),
    ];
}

function bump_invite_use(int $inviteId): void
{
    global $pdo;
    $pdo->prepare('UPDATE upload_invites SET used_count = used_count + 1 WHERE id = ?')->execute([$inviteId]);
}
