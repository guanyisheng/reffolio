-- 用户管理员权限（可重复执行）
-- 若曾报 #1060 Duplicate column name 'is_admin'，说明列已存在，可忽略 ADD 步骤。
USE reffolio;

SET @col_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'is_admin'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1=管理员'' AFTER display_name',
  'SELECT ''is_admin 列已存在，跳过 ADD COLUMN'' AS migration_note'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 首个用户（通常为 id=1）设为管理员
UPDATE users SET is_admin = 1 WHERE id = 1 LIMIT 1;

-- 若尚无管理员，则把最早注册的用户设为管理员
UPDATE users SET is_admin = 1
WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM users) AS t)
  AND NOT EXISTS (SELECT 1 FROM users WHERE is_admin = 1);
