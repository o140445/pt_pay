-- member 添加 google_token, is_bind_google, is_verify_google, new_google_token

ALTER TABLE `fa_member` ADD COLUMN `google_token` VARCHAR(255) NULL DEFAULT "" COMMENT 'google_token';
ALTER TABLE `fa_member` ADD COLUMN `is_bind_google` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否绑定google';
ALTER TABLE `fa_member` ADD COLUMN `is_verify_google` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否验证google';
ALTER TABLE `fa_member` ADD COLUMN `new_google_token` VARCHAR(255) NULL DEFAULT "" COMMENT 'new_google_token';

-- fa_withdraw_order 添加汇率
ALTER TABLE `fa_withdraw_order` ADD COLUMN `rate` DECIMAL(10, 2) NOT NULL DEFAULT 0 COMMENT '汇率';

-- fa_member 添加 是否开启网页代付 删除 docking_type
ALTER TABLE `fa_member` ADD COLUMN `is_open_web_pay` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否开启网页代付';
ALTER TABLE `fa_member` DROP COLUMN `docking_type`;

-- fa_channel 修改字段长度 mch_key 255 -> 2056 extra 255 -> 1024
ALTER TABLE `fa_channel` MODIFY COLUMN `mch_key` VARCHAR(2056) NOT NULL DEFAULT '' COMMENT '商户密钥';
ALTER TABLE `fa_channel` MODIFY COLUMN `extra` VARCHAR(1024) NOT NULL DEFAULT '' COMMENT '扩展参数';

-- order_out_delay add retry_count
ALTER TABLE `fa_order_out_delay` ADD COLUMN `retry_count` INT(11) NOT NULL DEFAULT 0 COMMENT '重试次数';

-- 机器人绑定表
CREATE TABLE `fa_robot_bind` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `member_id` INT(11) NOT NULL COMMENT '会员ID',
  `robot_id` varchar(64) NOT NULL COMMENT '机器人ID',
  `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_member_robot` (`member_id`, `robot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='机器人绑定表';