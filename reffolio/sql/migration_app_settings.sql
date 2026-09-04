-- 已有数据库时单独执行本文件，增加设置表
USE reffolio;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key   VARCHAR(128) NOT NULL,
  setting_value TEXT,
  update_time   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
