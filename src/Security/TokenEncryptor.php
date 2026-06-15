<?php
declare(strict_types=1);

namespace bleeld\logins\Security;

/**
 * Token加密器
 * 用于加密存储access_token和refresh_token
 */
class TokenEncryptor
{
    /**
     * 加密token
     */
    public static function encrypt(string $token, string $key): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密token
     */
    public static function decrypt(string $encryptedToken, string $key): string
    {
        $data = base64_decode($encryptedToken);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * 生成加密密钥
     */
    public static function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
