-- 稿件分类
USE reffolio;

CREATE TABLE IF NOT EXISTS work_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(64)  NOT NULL,
  sort        INT          NOT NULL DEFAULT 0,
  create_time DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_categories_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_work_categories_user (user_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE works
  ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER character_id,
  ADD KEY idx_works_category (category_id),
  ADD CONSTRAINT fk_works_category
    FOREIGN KEY (category_id) REFERENCES work_categories(id) ON DELETE SET NULL;
