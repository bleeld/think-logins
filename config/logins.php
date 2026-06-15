<?php
/**
 * 三方登录配置文件
 */
return [
    // 默认登录驱动
    'default' => 'wechat',

    // QQ登录配置
    'qq' => [
        'app_id' => '',          // QQ互联应用ID
        'app_key' => '',         // QQ互联应用Key
        'scope' => 'get_user_info',
    ],

    // 微信登录配置
    'wechat' => [
        'app_id' => '',          // 微信公众号AppID
        'app_secret' => '',      // 微信公众号AppSecret
        'scope' => 'snsapi_userinfo',  // snsapi_base(静默) 或 snsapi_userinfo(需要用户同意)
    ],

    // 微博登录配置
    'weibo' => [
        'app_id' => '',          // 微博应用Key
        'app_secret' => '',      // 微博应用Secret
    ],

    // 飞书登录配置
    'feishu' => [
        'app_id' => '',          // 飞书应用ID
        'app_secret' => '',      // 飞书应用Secret
    ],

    // 钉钉登录配置
    'dingtalk' => [
        'app_id' => '',          // 钉钉应用ID (AppKey)
        'app_secret' => '',      // 钉钉应用Secret (AppSecret)
    ],

    // 安全配置
    'security' => [
        // CSRF防护
        'enable_csrf_guard' => true,
        'state_expire_time' => 600,  // state过期时间（秒）

        // Token加密密钥（用于加密存储access_token）
        'token_encrypt_key' => '',  // 留空则自动生成

        // IP绑定验证
        'enable_ip_bind' => false,
    ],

    // 回调地址配置
    'redirect_uri' => [
        'qq' => '',
        'wechat' => '',
        'weibo' => '',
        'feishu' => '',
        'dingtalk' => '',
    ],

    // 自动绑定配置
    'auto_bind' => [
        // 是否自动创建本地账号
        'auto_create_user' => true,

        // 默认用户名前缀
        'username_prefix' => 'tp_',

        // 默认密码（随机生成）
        'default_password_length' => 16,
    ],

    // 日志配置
    'log_channel' => 'login',
];
