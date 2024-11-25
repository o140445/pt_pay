
-- 渠道
create TABLE if not exists fa_channel (
    `id` int NOT NULL AUTO_INCREMENT,
    `title` varchar(255)  NOT NULL COMMENT '通道名称',
    `code` varchar(255) NOT NULL COMMENT '通道编码',
    `sign` varchar(255)  NOT NULL COMMENT '签名',
    `mch_id` varchar(255)  NOT NULL COMMENT '商户id',
    `mch_key` varchar(255)  NOT NULL COMMENT '商户key',
    `gateway` varchar(255)  NOT NULL COMMENT '网关',
    `is_in` smallint DEFAULT 1 COMMENT '是否开启入款',
    `is_out` smallint DEFAULT 1 COMMENT '是否开启出款',
    `status` smallint DEFAULT 1 COMMENT '状态',
    `min_amount` decimal(10,4) DEFAULT 0 COMMENT '最小金额',
    `max_amount` decimal(10,4) DEFAULT 0 COMMENT '最大金额',
    `in_rate` decimal(10,4) DEFAULT 0 COMMENT '入款费率',
    `out_rate` decimal(10,4) DEFAULT 0 COMMENT '出款费率',
    `in_fixed_rate` decimal(10,4) DEFAULT 0 COMMENT '入款固定费率',
    `out_fixed_rate` decimal(10,4) DEFAULT 0 COMMENT '出款固定费率',
    `area_id` int  DEFAULT 0 COMMENT '地区id',
    `extra` varchar(500)  DEFAULT NULL COMMENT '额外配置',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    UNIQUE KEY `sign` (`sign`),
    INDEX `create_time` (`create_time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 通道
create TABLE if not exists fa_project (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(255)  NOT NULL COMMENT '项目名称',
    `area_id` int NOT NULL DEFAULT 0 COMMENT '地区id',
    `extend`  varchar(500)  NOT NULL COMMENT '扩展配置',
    `status` smallint DEFAULT 1 COMMENT '状态',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1001 DEFAULT CHARSET=utf8;

-- 通道渠道关联
create TABLE if not exists fa_project_channel (
    `id` int NOT NULL AUTO_INCREMENT,
    `project_id` int NOT NULL COMMENT '项目id',
    `channel_id` int NOT NULL COMMENT '通道id',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 会员
create TABLE if not exists fa_member (
    `id` int NOT NULL AUTO_INCREMENT,
    `username` varchar(255)  NOT NULL COMMENT '用户名',
    `password` varchar(255)  NOT NULL COMMENT '密码',
    `salt` varchar(255)  NOT NULL COMMENT '盐',
    `status` smallint Default 1 COMMENT '状态',
    `token` varchar(255) NOT NULL DEFAULT '' COMMENT 'token',
    `api_key` varchar(255)  NOT NULL COMMENT 'api_key',
    `is_sandbox` smallint Default 0 COMMENT '是否沙箱',
    `is_agency` smallint Default 0 COMMENT '是否代理',
    `agency_id` int Default 0 COMMENT '代理id',
    `area_id` int Default 0 COMMENT '地区id',
    `ip_white_list` varchar(255)  default '' COMMENT 'ip白名单',
    `usdt_address` varchar(255)  default '' COMMENT 'usdt地址',
    `docking_type` tinyint Default 0 COMMENT '对接类型 1:api 0:手动',
    `last_login_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '最后登录时间',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    UNIQUE KEY `username` (`username`),
    INDEX `create_time` (`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=90001 DEFAULT CHARSET=utf8mb4 COMMENT='会员表';

-- 会员钱包
create TABLE if not exists fa_member_wallet (
    `id` int NOT NULL AUTO_INCREMENT,
    `member_id` int NOT NULL COMMENT '会员id',
    `balance` decimal(10,4) Default 0 COMMENT '余额',
    `blocked_balance` decimal(10,4) Default 0 COMMENT '冻结余额',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员钱包表';

-- 会员余额变动记录
create TABLE if not exists fa_member_wallet_log (
    `id` int NOT NULL AUTO_INCREMENT,
    `member_id` int NOT NULL COMMENT '会员id',
    `order_no` varchar(255)  NOT NULL COMMENT '业务单号',
    `amount` decimal(10,4) Default 0 COMMENT '变动金额',
    `before_balance` decimal(10,4) Default 0 COMMENT '变动前余额',
    `after_balance` decimal(10,4) Default 0 COMMENT '变动后余额',
    `type` varchar(255)  NOT NULL COMMENT '类型',
    `remark` varchar(255)  NOT NULL COMMENT '备注',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (id),
    KEY `member_id` (`member_id`),
    KEY `order_no` (`order_no`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员余额变动记录表';

-- 会员冻结列表
create TABLE if not exists fa_member_wallet_freeze (
    `id` int NOT NULL AUTO_INCREMENT,
    `member_id` int NOT NULL COMMENT '会员id',
    `order_no` varchar(255)  NOT NULL COMMENT '业务单号',
    `amount` decimal(10,4) Default 0 COMMENT '冻结金额',
    `remark` varchar(255)  NOT NULL COMMENT '备注',
    `freeze_type` tinyint Default 0 COMMENT '冻结类型',
    `status` smallint Default 0 COMMENT '状态',
    `thaw_time` timestamp  COMMENT '解冻时间',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (id),
    KEY `member_id` (`member_id`),
    KEY `order_no` (`order_no`),
    KEY `create_time` (`create_time`),
    KEY `thaw_time` (`thaw_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员冻结列表';


-- 会员通道费率
create TABLE if not exists fa_member_project_channel (
   `id` int NOT NULL AUTO_INCREMENT,
   `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态',
   `member_id` bigint NOT NULL,
   `project_id` bigint NOT NULL,
   `type` tinyint NOT NULL DEFAULT '1' COMMENT '1代收 2代付',
   `fixed_rate` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '固定成本',
    `rate` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000' COMMENT '成本比例',
    `channel_id` bigint NOT NULL DEFAULT '0' COMMENT '通道id',
    `sub_member_id` int NOT NULL DEFAULT '0' COMMENT '子商户号',
    PRIMARY KEY (`id`),
    INDEX `member_id_project_id` (`member_id`, `project_id`),
    INDEX `channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员通道费率';

-- 提款单
create TABLE if not exists fa_withdraw_order (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(255)  NOT NULL COMMENT '订单号',
    `member_id` int NOT NULL COMMENT '会员id',
    `amount` decimal(10,4) Default 0 COMMENT '金额',
    `usdt_amount` decimal(10,4) Default 0 COMMENT 'usdt金额',
    `usdt_address` varchar(255)  NOT NULL COMMENT 'usdt地址',
    `status` smallint Default 0 COMMENT '状态',
    `remark` varchar(255)  NOT NULL COMMENT '备注',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    KEY `order_no` (`order_no`),
    KEY `member_id` (`member_id`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='提款单';


-- 代收单
create TABLE if not exists fa_order_in (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(255)  NOT NULL COMMENT '订单号',
    `member_order_no` varchar(255)  NOT NULL COMMENT '会员订单号',
    `channel_order_no` varchar(255)  NOT NULL COMMENT '通道订单号',
    `member_id` int NOT NULL COMMENT '会员id',
    `amount` decimal(10,4) Default 0 COMMENT '金额',
    `true_amount` decimal(10,4) Default 0 COMMENT '实际金额',
    `actual_amount` decimal(10,4) Default 0 COMMENT '实际到账金额',
    `fee_amount` decimal(10,4) Default 0 COMMENT '手续费',
    `channel_fee_amount` decimal(10,4) Default 0 COMMENT '通道手续费',
    `project_id` int NOT NULL COMMENT '项目id',
    `channel_id` int NOT NULL COMMENT '通道id',
    `pay_url` varchar(500) Default '' NOT NULL COMMENT '支付地址',
    `attach` varchar(255) Default '' NOT NULL COMMENT '附加信息',
    `order_ip` varchar(255) Default '' NOT NULL COMMENT '订单ip',
    `error_msg` varchar(255) Default '' NOT NULL COMMENT '消息',
    `e_no` varchar(255) Default '' NOT NULL COMMENT 'E单号',
    `status` smallint Default 0 COMMENT '状态',
    `remark` varchar(255) Default '' NOT NULL COMMENT '备注',
    `pay_success_date` timestamp  COMMENT '支付成功时间',
    `notify_url` varchar(500) Default '' NOT NULL COMMENT '通知地址',
    `notify_status` smallint Default 0 COMMENT '通知状态',
    `notify_count` int Default 0 COMMENT '通知次数',
    `area_id` int Default 0 COMMENT '地区id',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    KEY `order_no` (`order_no`),
    KEY `member_id` (`member_id`),
    KEY `area_id` (`area_id`),
    KEY `member_order_no` (`member_order_no`),
    KEY `channel_order_no` (`channel_order_no`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代付单';



-- 地区信息
create TABLE if not exists fa_config_area (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(255)  NOT NULL COMMENT '地区名称',
    `timezone` varchar(255)  NOT NULL COMMENT '时区',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='地区信息';


CREATE TABLE `fa_profit` (
     `id` int NOT NULL AUTO_INCREMENT,
     `area_id` bigint NOT NULL DEFAULT '0' COMMENT '区域ID',
     `member_id` bigint NOT NULL DEFAULT '0' COMMENT '会员ID',
     `order_no` varchar(50) NOT NULL COMMENT '订单单号',
     `order_type` tinyint DEFAULT '1' COMMENT '来源类型 1代收 2代付',
     `order_amount` decimal(15,4) NOT NULL COMMENT '订单金额',
     `fee` decimal(15,4) NOT NULL COMMENT '手续费',
     `channel_fee` decimal(15,4) NOT NULL COMMENT '上游手续费',
     `commission` decimal(15,4) NOT NULL COMMENT '提成',
     `profit` decimal(15,4) NOT NULL COMMENT '利润',
     `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
     `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
     PRIMARY KEY (`id`),
     KEY `idx_order_no` (`order_no`),
     KEY `idx_member_id` (`member_id`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='利润报表';

-- 回调下游记录
CREATE TABLE `fa_notify_log` (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(50) NOT NULL COMMENT '订单单号',
    `notify_type` tinyint NOT NULL DEFAULT '1' COMMENT '通知类型 1代收 2代付',
    `notify_url` varchar(255) NOT NULL COMMENT '通知地址',
    `notify_data` text NOT NULL COMMENT '通知数据',
    `notify_result` text NOT NULL COMMENT '通知结果',
    `notify_status` tinyint NOT NULL DEFAULT '0' COMMENT '通知状态',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='回调下游记录';



-- 代付单
create TABLE if not exists fa_order_out (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(255)  NOT NULL COMMENT '订单号',
    `member_id` int NOT NULL COMMENT '会员id',
    `member_order_no` varchar(255)  NOT NULL COMMENT '会员订单号',
    `channel_order_no` varchar(255)  NOT NULL COMMENT '通道订单号',
    `amount` decimal(10,4) Default 0 COMMENT '金额',
    `actual_amount` decimal(10,4) Default 0 COMMENT '实际到账金额',
    `fee_amount` decimal(10,4) Default 0 COMMENT '手续费',
    `channel_fee_amount` decimal(10,4) Default 0 COMMENT '通道手续费',
    `project_id` int NOT NULL COMMENT '项目id',
    `channel_id` int NOT NULL COMMENT '通道id',
    `order_ip` varchar(255) Default '' NOT NULL COMMENT '订单ip',
    `error_msg` varchar(255) Default '' NOT NULL COMMENT '消息',
    `e_no` varchar(255) Default '' NOT NULL COMMENT 'E单号',
    `status` smallint Default 0 COMMENT '状态',
    `extra` text  NOT NULL COMMENT '额外信息',
    `pay_success_date` timestamp  COMMENT '支付成功时间',
    `notify_url` varchar(500) Default '' NOT NULL COMMENT '通知地址',
    `notify_status` smallint Default 0 COMMENT '通知状态',
    `notify_count` int Default 0 COMMENT '通知次数',
    `area_id` int Default 0 COMMENT '地区id',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (id),
    KEY `order_no` (`order_no`),
    KEY `member_id` (`member_id`),
    KEY `member_no` (`member_order_no`),
    KEY `channel_no` (`channel_order_no`),
    KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代付单';



-- 单据请求返回记录
CREATE TABLE `fa_order_request_log` (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(50) NOT NULL COMMENT '订单单号',
    `order_type` tinyint NOT NULL DEFAULT '1' COMMENT '订单类型 1代收 2代付',
    `request_type` tinyint NOT NULL DEFAULT '1' COMMENT '请求类型 1请求 2返回',
    `request_data` text NOT NULL COMMENT '请求数据',
    `response_data` text NOT NULL COMMENT '返回数据',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='单据请求返回记录';

-- 利润报表统计
CREATE TABLE `fa_profit_stat` (
    `id` int NOT NULL AUTO_INCREMENT,
    `area_id` bigint NOT NULL DEFAULT '0' COMMENT '区域ID',
    `in_order_count` int NOT NULL DEFAULT '0' COMMENT '代收订单数',
    `in_order_amount` decimal(15,4) NOT NULL COMMENT '代收订单金额',
    `in_fee` decimal(15,4) NOT NULL COMMENT '代收手续费',
    `in_channel_fee` decimal(15,4) NOT NULL COMMENT '代收上游手续费',
    `in_commission` decimal(15,4) NOT NULL COMMENT '代收提成',
    `in_profit` decimal(15,4) NOT NULL COMMENT '代收利润',
    `out_order_count` int NOT NULL DEFAULT '0' COMMENT '代付订单数',
    `out_order_amount` decimal(15,4) NOT NULL COMMENT '代付订单金额',
    `out_fee` decimal(15,4) NOT NULL COMMENT '代付手续费',
    `out_channel_fee` decimal(15,4) NOT NULL COMMENT '代付上游手续费',
    `out_commission` decimal(15,4) NOT NULL COMMENT '代付提成',
    `out_profit` decimal(15,4) NOT NULL COMMENT '代付利润',
    `profit` decimal(15,4) NOT NULL COMMENT '利润',
    `date` date NOT NULL COMMENT '日期',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='利润报表统计';

-- 商户每日统计
CREATE TABLE `fa_member_stat` (
    `id` int NOT NULL AUTO_INCREMENT,
    `member_id` bigint NOT NULL DEFAULT '0' COMMENT '会员ID',
    `in_order_count` int NOT NULL DEFAULT '0' COMMENT '代收订单数',
    `in_order_success_count` int NOT NULL DEFAULT '0' COMMENT '代收成功订单数',
    `in_order_amount` decimal(15,4) NOT NULL COMMENT '代收订单金额',
    `in_order_success_amount` decimal(15,4) NOT NULL COMMENT '代收成功订单金额',
    `in_fee` decimal(15,4) NOT NULL COMMENT '代收手续费',
    `in_success_rate` decimal(15,4) NOT NULL COMMENT '代收成功率',
    `out_order_count` int NOT NULL DEFAULT '0' COMMENT '代付订单数',
    `out_order_success_count` int NOT NULL DEFAULT '0' COMMENT '代付成功订单数',
    `out_order_amount` decimal(15,4) NOT NULL COMMENT '代付订单金额',
    `out_order_success_amount` decimal(15,4) NOT NULL COMMENT '代付成功订单金额',
    `out_fee` decimal(15,4) NOT NULL COMMENT '代付手续费',
    `out_success_rate` decimal(15,4) NOT NULL COMMENT '代付成功率',
    `date` date NOT NULL COMMENT '日期',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商户每日统计';

-- 渠道每日统计
CREATE TABLE `fa_channel_stat` (
    `id` int NOT NULL AUTO_INCREMENT,
    `channel_id` bigint NOT NULL DEFAULT '0' COMMENT '通道ID',
    `date` date NOT NULL COMMENT '日期',
    `in_order_count` int NOT NULL DEFAULT '0' COMMENT '代收订单数',
    `in_order_success_count` int NOT NULL DEFAULT '0' COMMENT '代收成功订单数',
    `in_order_amount` decimal(15,4) NOT NULL COMMENT '代收订单金额',
    `in_order_success_amount` decimal(15,4) NOT NULL COMMENT '代收成功订单金额',
    `in_channel_fee` decimal(15,4) NOT NULL COMMENT '代收上游手续费',
    `in_success_rate` decimal(15,4) NOT NULL COMMENT '代收成功率',
    `out_order_count` int NOT NULL DEFAULT '0' COMMENT '代付订单数',
    `out_order_success_count` int NOT NULL DEFAULT '0' COMMENT '代付成功订单数',
    `out_order_amount` decimal(15,4) NOT NULL COMMENT '代付订单金额',
    `out_order_success_amount` decimal(15,4) NOT NULL COMMENT '代付成功订单金额',
    `out_channel_fee` decimal(15,4) NOT NULL COMMENT '代付上游手续费',
    `out_success_rate` decimal(15,4) NOT NULL COMMENT '代付成功率',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='渠道每日统计';


-- 手动订单
CREATE TABLE `fa_order_manual` (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(50) NOT NULL COMMENT '订单单号',
    `channel_order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '通道订单号',
    `amount` decimal(15,4) NOT NULL COMMENT '订单金额',
    `channel_id` bigint NOT NULL DEFAULT '0' COMMENT '通道ID',
    `area_id` bigint NOT NULL DEFAULT '0' COMMENT '区域ID',
    `status` tinyint NOT NULL DEFAULT '0' COMMENT '订单状态 0未支付 1已支付 2支付失败 3退款',
    `extra` text NOT NULL COMMENT '订单数据',
    `e_no` varchar(50) NOT NULL  DEFAULT '' COMMENT 'E单号',
    `msg` varchar(255) NOT NULL   DEFAULT '' COMMENT '消息',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_create_time` (`create_time`),
    KEY `idx_channel_id` (`channel_id`),
    KEY `idx_channel_order_no` (`channel_order_no`),
    KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='手动订单';

-- 沙盒订单
create TABLE if not exists `fa_order_sandbox` (
    `id` int NOT NULL AUTO_INCREMENT,
    `order_no` varchar(50) NOT NULL COMMENT '订单单号',
    `member_id` bigint NOT NULL DEFAULT '0' COMMENT '会员ID',
    `member_order_no` varchar(50) NOT NULL COMMENT '会员订单号',
    `amount` decimal(15,4) NOT NULL COMMENT '订单金额',
    `project_id` bigint NOT NULL DEFAULT '0' COMMENT '项目ID',
    `status` tinyint NOT NULL DEFAULT '0' COMMENT '订单状态 0未支付 1已支付 2支付失败 3退款',
    `notify_url` varchar(255) NOT NULL COMMENT '通知地址',
    `notify_status` tinyint NOT NULL DEFAULT '0' COMMENT '通知状态',
    `notify_count` int NOT NULL DEFAULT '0' COMMENT '通知次数',
    `msg` varchar(255) NOT NULL   DEFAULT '' COMMENT '消息',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_create_time` (`create_time`),
    KEY `idx_member_id` (`member_id`),
    KEY `idx_order_no` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='沙盒订单';

-- 延迟代付回调
CREATE TABLE `fa_order_out_delay` (
    `id` int NOT NULL AUTO_INCREMENT,
    `source` varchar(50) NOT NULL COMMENT '来源',
    `data` text NOT NULL COMMENT '数据',
    `status` tinyint NOT NULL DEFAULT '0' COMMENT '状态',
    `create_time` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `update_time` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    KEY `idx_create_time` (`create_time`),
    key `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='延迟代付回调';