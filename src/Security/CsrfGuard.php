<?php
declare(strict_types=1);

namespace bleeld\logins\Security;

/**
 * CSRF防护
 * 通过state参数防止跨站请求伪造攻击
 */
class CsrfGuard
{
    /**
     * 生成state
     */
    public static function generateState(): string
    {
        $state = bin2hex(random_bytes(32));
        
        // 存储到session，设置过期时间
        if (function_exists('session')) {
            session('oauth_state_' . $state, [
                'value' => $state,
                'time' => time(),
            ]);
        }
        
        return $state;
    }

    /**
     * 验证state
     */
    public static function verifyState(string $state, int $expireTime = 600): bool
    {
        if (!function_exists('session')) {
            return true; // 如果没有session功能，跳过验证
        }
        
        $storedData = session('oauth_state_' . $state);
        
        if (!$storedData) {
            return false;
        }
        
        // 检查是否过期
        if (time() - $storedData['time'] > $expireTime) {
            session('oauth_state_' . $state, null);
            return false;
        }
        
        // 验证通过后删除（一次性使用）
        session('oauth_state_' . $state, null);
        
        return $storedData['value'] === $state;
    }

    /**
     * 存储state
     */
    public static function storeState(string $state): void
    {
        if (function_exists('session')) {
            session('oauth_state_' . $state, [
                'value' => $state,
                'time' => time(),
            ]);
        }
    }

    /**
     * 清理过期state
     */
    public static function cleanup(int $expireTime = 600): void
    {
        // Session会自动清理，这里可以添加额外的清理逻辑
    }
}
