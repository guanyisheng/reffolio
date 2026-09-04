<?php
/**
 * 稿件分类
 */
declare(strict_types=1);

function get_user_categories(int $userId): array
{
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM works w
                     INNER JOIN characters ch ON ch.id = w.character_id
                     WHERE w.category_id = c.id AND ch.user_id = ?) AS work_count
             FROM work_categories c
             WHERE c.user_id = ?
             ORDER BY c.sort ASC, c.id ASC'
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_category_for_user(int $categoryId, int $userId): ?array
{
    global $pdo;
    if ($categoryId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM work_categories WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$categoryId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function resolve_work_category_id(int $userId, mixed $raw): ?int
{
    $id = (int) $raw;
    if ($id <= 0) {
        return null;
    }
    return get_category_for_user($id, $userId) ? $id : null;
}

function category_name(?array $category): string
{
    if (!$category) {
        return '';
    }
    return row_str($category, 'name');
}

function work_category_label(?int $categoryId, int $userId): string
{
    if ($categoryId === null || $categoryId <= 0) {
        return '未分类';
    }
    $cat = get_category_for_user($categoryId, $userId);
    return $cat ? row_str($cat, 'name') : '未分类';
}

function create_work_category(int $userId, string $name): int
{
    global $pdo;
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('请填写分类名称。');
    }
    if (mb_strlen($name) > 64) {
        throw new InvalidArgumentException('分类名称最多 64 字。');
    }
    $max = $pdo->prepare('SELECT COALESCE(MAX(sort), -1) FROM work_categories WHERE user_id = ?');
    $max->execute([$userId]);
    $sort = (int) $max->fetchColumn() + 1;
    $stmt = $pdo->prepare('INSERT INTO work_categories (user_id, name, sort) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $name, $sort]);
    return (int) $pdo->lastInsertId();
}

function delete_work_category(int $userId, int $categoryId): void
{
    global $pdo;
    $cat = get_category_for_user($categoryId, $userId);
    if (!$cat) {
        throw new InvalidArgumentException('分类不存在。');
    }
    $pdo->prepare('UPDATE works w INNER JOIN characters c ON c.id = w.character_id SET w.category_id = NULL WHERE w.category_id = ? AND c.user_id = ?')
        ->execute([$categoryId, $userId]);
    $pdo->prepare('DELETE FROM work_categories WHERE id = ? AND user_id = ?')->execute([$categoryId, $userId]);
}

function render_category_select(int $userId, ?int $selectedId, string $fieldName = 'category_id', bool $required = false): void
{
    $categories = get_user_categories($userId);
    $req = $required ? ' required' : '';
    echo '<div class="form-row">';
    echo '<label for="' . e($fieldName) . '">稿件分类</label>';
    echo '<select id="' . e($fieldName) . '" name="' . e($fieldName) . '"' . $req . '>';
    $noneSel = ($selectedId === null || $selectedId === 0) ? ' selected' : '';
    echo '<option value=""' . $noneSel . '>未分类</option>';
    foreach ($categories as $cat) {
        $cid = (int) $cat['id'];
        $sel = ($selectedId !== null && $selectedId === $cid) ? ' selected' : '';
        echo '<option value="' . $cid . '"' . $sel . '>' . e(row_str($cat, 'name')) . '</option>';
    }
    echo '</select>';
    if (!$categories) {
        echo '<span class="hint">暂无分类，可先在 <a href="/categories.php">分类管理</a> 中创建。</span>';
    }
    echo '</div>';
}
