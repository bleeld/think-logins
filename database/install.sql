-- 三方账号绑定表
CREATE TABLE IF NOT EXISTS `system_third_party_account` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '本地用户ID',
  `driver` varchar(20) NOT NULL COMMENT '登录驱动：qq/wechat/weibo/feishu/dingtalk',
  `open_id` varchar(100) NOT NULL COMMENT '开放平台ID',
  `union_id` varchar(100) DEFAULT '' COMMENT '统一平台ID（微信等平台使用）',
  `access_token` text COMMENT '访问令牌（加密存储）',
  `refresh_token` text COMMENT '刷新令牌（加密存储）',
  `token_expires_at` int(11) unsigned DEFAULT 0 COMMENT 'token过期时间戳',
  `user_info` text COMMENT '用户信息JSON',
  `last_login_time` int(11) unsigned DEFAULT 0 COMMENT '最后登录时间',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `create_time` int(11) unsigned DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) unsigned DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_driver_openid` (`driver`, `open_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='三方账号绑定表';
