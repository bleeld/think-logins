<?php
declare(strict_types=1);

namespace bleeld\logins;

/**
 * 登录驱动接口
 * 所有登录驱动必须实现此接口
 */
interface DriverInterface
{
    /**
     * 设置配置
     * 
     * @param array $config 驱动配置
     * @return void
     */
    public function setConfig(array $config): void;

    /**
     * 获取授权URL（跳转到三方平台）
     * 
     * @param string $state CSRF防护状态码
     * @param string $redirectUri 回调地址
     * @return string 授权URL
     */
    public function getAuthUrl(string $state, string $redirectUri): string;

    /**
     * 通过code获取access_token和用户信息
     * 
     * @param string $code 授权码
     * @param string $redirectUri 回调地址
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function getAccessToken(string $code, string $redirectUri): array;

    /**
     * 获取用户信息
     * 
     * @param string $accessToken 访问令牌
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function getUserInfo(string $accessToken): array;

    /**
     * 刷新token
     * 
     * @param string $refreshToken 刷新令牌
     * @return array ['code' => 1/0, 'msg' => '消息', 'data' => [...]]
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * 验证state参数（防CSRF）
     * 
     * @param string $state 状态码
     * @return bool
     */
    public function verifyState(string $state): bool;

    /**
     * 获取驱动名称
     * 
     * @return string
     */
    public function getName(): string;

    /**
     * 获取驱动类型（oauth2/qrcode/app等）
     * 
     * @return string
     */
    public function getType(): string;
}
