-- 角色单独头像（char-avatar），与封面 cover_image 可分别设置
USE reffolio;

ALTER TABLE characters
  ADD COLUMN avatar_image VARCHAR(512) DEFAULT NULL COMMENT '角色头像，空则沿用封面'
  AFTER cover_image;
