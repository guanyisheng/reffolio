<?php
/**
 * Session 用户认证
 */
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, display_name, is_admin, create_time FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    $cached = $user ?: null;
    if ($cached === null) {
        unset($_SESSION['user_id']);
    }
    return $cached;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', '请先登录。');
        redirect('/login.php');
    }
    return $user;
}

function is_admin(?array $user = null): bool
{
    $user = $user ?? current_user();
    if ($user === null) {
        return false;
    }
    return !empty($user['is_admin']);
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        flash('error', '仅管理员可访问此功能。');
        redirect('/characters.php');
    }
    return $user;
}

function count_admins(): int
{
    global $pdo;
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1');
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function list_all_users(): array
{
    global $pdo;
    $stmt = $pdo->query(
        'SELECT id, username, display_name, is_admin, create_time
         FROM users
         ORDER BY id ASC'
    );
    return $stmt->fetchAll();
}

function set_user_admin(int $targetUserId, bool $asAdmin): void
{
    global $pdo;
    $current = require_admin();

    if ($targetUserId <= 0) {
        throw new InvalidArgumentException('无效的用户。');
    }

    $stmt = $pdo->prepare('SELECT id, is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $target = $stmt->fetch();
    if (!$target) {
        throw new InvalidArgumentException('用户不存在。');
    }

    $wasAdmin = !empty($target['is_admin']);
    if ($wasAdmin === $asAdmin) {
        return;
    }

    if ($wasAdmin && !$asAdmin) {
        if ((int) $targetUserId === (int) $current['id']) {
            throw new InvalidArgumentException('不能取消自己的管理员权限。');
        }
        if (count_admins() <= 1) {
            throw new InvalidArgumentException('系统至少保留一名管理员。');
        }
    }

    $stmt = $pdo->prepare('UPDATE users SET is_admin = ? WHERE id = ?');
    $stmt->execute([$asAdmin ? 1 : 0, $targetUserId]);
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function register_user(string $username, string $password, ?string $displayName = null): int
{
    global $pdo;
    $username = trim($username);
    if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 64) {
        throw new InvalidArgumentException('用户名长度需为 3–64 个字符。');
    }
    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        throw new InvalidArgumentException('用户名仅允许字母、数字、下划线与中文。');
    }
    if (strlen($password) < 6) {
        throw new InvalidArgumentException('密码至少 6 位。');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $makeAdmin = count_admins() === 0 ? 1 : 0;
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, is_admin) VALUES (?, ?, ?, ?)');
    try {
        $stmt->execute([$username, $hash, $displayName ?: $username, $makeAdmin]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            throw new InvalidArgumentException('用户名已存在。');
        }
        throw $e;
    }
    return (int) $pdo->lastInsertId();
}

function attempt_login(string $username, string $password): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    login_user((int) $row['id']);
    return true;
}

function user_owns_character(int $userId, int $characterId): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT 1 FROM characters WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$characterId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function user_owns_work(int $userId, int $workId): bool
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT 1 FROM works w
         INNER JOIN characters c ON c.id = w.character_id
         WHERE w.id = ? AND c.user_id = ? LIMIT 1'
    );
    $stmt->execute([$workId, $userId]);
    return (bool) $stmt->fetchColumn();
}
