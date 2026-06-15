<?php
declare(strict_types=1);

namespace bleeld\logins\Security;

/**
 * IP绑定验证
 * 用于将token与IP地址绑定，防止token被盗用
 */
class IpBinder
{
    /**
     * 绑定IP到token
     */
    public static function bind(string $token, string $ip): void
    {
        if (function_exists('cache')) {
            cache('ip_bind_' . md5($token), $ip, 7200);
        }
    }

    /**
     * 验证IP是否匹配
     */
    public static function verify(string $token, string $ip): bool
    {
        if (!function_exists('cache')) {
            return true; // 如果没有cache功能，跳过验证
        }
        
        $boundIp = cache('ip_bind_' . md5($token));
        
        if (!$boundIp) {
            return true; // 未绑定IP时放行
        }
        
        return $boundIp === $ip;
    }

    /**
     * 清除绑定
     */
    public static function clear(string $token): void
    {
        if (function_exists('cache')) {
            cache('ip_bind_' . md5($token), null);
        }
    }
}
