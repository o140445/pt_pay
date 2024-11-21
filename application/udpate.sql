-- member 添加 google_token, is_bind_google, is_verify_google, new_google_token

ALTER TABLE `fa_member` ADD COLUMN `google_token` VARCHAR(255) NULL DEFAULT "" COMMENT 'google_token';
ALTER TABLE `fa_member` ADD COLUMN `is_bind_google` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否绑定google';
ALTER TABLE `fa_member` ADD COLUMN `is_verify_google` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否验证google';
ALTER TABLE `fa_member` ADD COLUMN `new_google_token` VARCHAR(255) NULL DEFAULT "" COMMENT 'new_google_token';

-- fa_withdraw_order 添加汇率
ALTER TABLE `fa_withdraw_order` ADD COLUMN `rate` DECIMAL(10, 2) NOT NULL DEFAULT 0 COMMENT '汇率';