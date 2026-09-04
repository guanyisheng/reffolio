-- ============================================================
-- 角色设定与稿件管理系统 — 数据库结构
-- MySQL 8+
-- ============================================================
--
-- ER 关系：
--
--   users (1) ────────< (N) characters
--   characters (1) ───< (N) character_images
--   characters (1) ───< (N) works
--   works (1) ────────< (N) work_images
--
-- 一个用户拥有多个角色设定；
-- 一个角色设定拥有多张主设图片、多个稿件；
-- 一个稿件拥有多张图片。
-- ============================================================

-- 若库已由面板创建，可直接导入本文件中的表结构。
-- 若有建库权限，也可取消下面两行注释（库名可按需修改）：
-- CREATE DATABASE IF NOT EXISTS reffolio
--   DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE reffolio;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(64)  DEFAULT NULL,
  is_admin      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=管理员',
  create_time   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 角色设定表
-- ----------------------------
CREATE TABLE IF NOT EXISTS characters (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(128) NOT NULL,
  description  TEXT,
  tags         VARCHAR(512) DEFAULT NULL COMMENT '逗号分隔标签',
  cover_image  VARCHAR(512) DEFAULT NULL,
  avatar_image VARCHAR(512) DEFAULT NULL COMMENT '角色头像，空则沿用封面',
  create_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  update_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_characters_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_characters_user (user_id),
  KEY idx_characters_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 角色主设图片表
-- ----------------------------
CREATE TABLE IF NOT EXISTS character_images (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  image_path   VARCHAR(512) NOT NULL,
  image_name   VARCHAR(255) DEFAULT NULL,
  description  TEXT,
  sort         INT          NOT NULL DEFAULT 0,
  create_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_character_images_character
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
  KEY idx_character_images_character (character_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 稿件分类
-- ----------------------------
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

-- ----------------------------
-- 稿件表
-- ----------------------------
CREATE TABLE IF NOT EXISTS works (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED DEFAULT NULL,
  title        VARCHAR(255) NOT NULL,
  description  TEXT,
  date         DATE         DEFAULT NULL,
  cover_image  VARCHAR(512) DEFAULT NULL,
  create_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  update_time  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_works_character
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_works_category
    FOREIGN KEY (category_id) REFERENCES work_categories(id) ON DELETE SET NULL,
  KEY idx_works_character (character_id),
  KEY idx_works_category (category_id),
  KEY idx_works_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 稿件图片表
-- ----------------------------
CREATE TABLE IF NOT EXISTS work_images (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_id           INT UNSIGNED NOT NULL,
  image_path        VARCHAR(512) NOT NULL,
  image_name        VARCHAR(255) DEFAULT NULL,
  image_description TEXT,
  sort              INT          NOT NULL DEFAULT 0,
  create_time       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_images_work
    FOREIGN KEY (work_id) REFERENCES works(id) ON DELETE CASCADE,
  KEY idx_work_images_work (work_id, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 应用设置（存储等，由用户在后台填写）
-- ----------------------------
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key   VARCHAR(128) NOT NULL,
  setting_value TEXT,
  update_time   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- 画师上传邀请链接
-- ----------------------------
CREATE TABLE IF NOT EXISTS upload_invites (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL COMMENT '发起人（角色主人）',
  character_id  INT UNSIGNED NOT NULL,
  token         VARCHAR(64)  NOT NULL,
  note          VARCHAR(512) DEFAULT NULL COMMENT '给画师的说明',
  artist_hint   VARCHAR(128) DEFAULT NULL COMMENT '备注画师称呼（仅主人可见）',
  max_uses      INT UNSIGNED DEFAULT NULL COMMENT 'NULL=不限次数',
  used_count    INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at    DATETIME     DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  create_time   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_upload_invites_token (token),
  KEY idx_upload_invites_user (user_id),
  KEY idx_upload_invites_character (character_id),
  CONSTRAINT fk_upload_invites_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_upload_invites_character
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
