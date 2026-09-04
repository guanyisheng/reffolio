-- 画师上传邀请链接
USE reffolio;

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
