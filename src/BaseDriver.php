<?php
declare(strict_types=1);

namespace bleeld\logins;

/**
 * 登录驱动基础抽象类
 * 提供所有驱动共用的功能
 */
abstract class BaseDriver implements DriverInterface
{
    /**
     * 驱动配置
     */
    protected array $config = [];

    /**
     * 驱动名称
     */
    protected string $name = '';

    /**
     * 驱动类型
     */
    protected string $type = 'oauth2';

    /**
     * 设置配置
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取配置项
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * HTTP请求封装
     */
    protected function httpRequest(
        string $url, 
        mixed $data = null, 
        string $method = 'GET', 
        array $headers = []
    ): string {
        $ch = curl_init();
        
        $timeout = $this->getConfig('timeout', 30);
        
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("HTTP请求失败: {$error}");
        }
        
        curl_close($ch);
        
        return $response;
    }

    /**
     * 生成随机state
     */
    protected function generateState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 日志记录
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('think')) {
            \think\facade\Log::record($message, $level);
        }
    }

    /**
     * 构建成功响应
     */
    protected function success(array $data = []): array
    {
        return ['code' => 1, 'msg' => 'success', 'data' => $data];
    }

    /**
     * 构建失败响应
     */
    protected function error(string $msg, array $data = []): array
    {
        return ['code' => 0, 'msg' => $msg, 'data' => $data];
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取驱动类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 验证state参数（默认实现，子类可重写）
     */
    public function verifyState(string $state): bool
    {
        // 默认从session中验证
        $storedState = session('oauth_state_' . $this->name);
        
        if (!$storedState) {
            return false;
        }
        
        // 检查是否过期
        $expireTime = $this->getConfig('state_expire_time', 600);
        if (time() - $storedState['time'] > $expireTime) {
            session('oauth_state_' . $this->name, null);
            return false;
        }
        
        // 验证通过后删除
        session('oauth_state_' . $this->name, null);
        
        return $storedState['value'] === $state;
    }
}
