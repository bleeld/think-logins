<?php
declare(strict_types=1);

namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;
use bleeld\logins\Exception\AuthFailedException;

/**
 * QQ登录驱动
 * 基于OAuth2.0协议实现
 */
class QqDriver extends BaseDriver
{
    protected string $name = 'qq';
    protected string $type = 'oauth2';

    /**
     * 获取授权URL
     */
    public function getAuthUrl(string $state, string $redirectUri): string
    {
        $appId = $this->getConfig('app_id');
        if (!$appId) {
            throw new AuthFailedException('QQ App ID未配置');
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $this->getConfig('scope', 'get_user_info'),
        ];

        return 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query($params);
    }

    /**
     * 通过code获取access_token和用户信息
     */
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $appId = $this->getConfig('app_id');
        $appKey = $this->getConfig('app_key');

        if (!$appId || !$appKey) {
            return $this->error('QQ App ID或App Key未配置');
        }

        // 第一步：获取access_token
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'client_id' => $appId,
            'client_secret' => $appKey,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $tokenUrl = 'https://graph.qq.com/oauth2.0/token?' . http_build_query($tokenParams);
        $response = $this->httpRequest($tokenUrl, null, 'GET');

        // QQ返回的是URL格式：access_token=xxx&expires_in=xxx
        parse_str($response, $tokenData);

        if (!isset($tokenData['access_token'])) {
            return $this->error('获取access_token失败: ' . ($tokenData['msg'] ?? '未知错误'));
        }

        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'] ?? 7776000;

        // 第二步：获取openid
        $openidResponse = $this->httpRequest(
            'https://graph.qq.com/oauth2.0/me?access_token=' . $accessToken,
            null,
            'GET'
        );

        // 提取openid（QQ返回 callback( {"client_id":"xxx","openid":"xxx"} ); ）
        preg_match('/"openid"\s*:\s*"([^"]+)"/', $openidResponse, $matches);
        if (!isset($matches[1])) {
            return $this->error('获取openid失败');
        }

        $openid = $matches[1];

        // 第三步：获取用户信息
        $userInfo = $this->getUserInfo($accessToken, $openid);

        if (!$userInfo) {
            return $this->error('获取用户信息失败');
        }

        return $this->success([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'openid' => $openid,
            'user_info' => $userInfo,
        ]);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(string $accessToken, string $openid): ?array
    {
        $appId = $this->getConfig('app_id');

        $params = [
            'access_token' => $accessToken,
            'oauth_consumer_key' => $appId,
            'openid' => $openid,
        ];

        $url = 'https://graph.qq.com/user/get_user_info?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        if (!$data || isset($data['ret']) && $data['ret'] != 0) {
            return null;
        }

        return [
            'openid' => $openid,
            'nickname' => $data['nickname'] ?? '',
            'avatar' => $data['figureurl_qq_2'] ?? $data['figureurl_2'] ?? '',
            'gender' => $data['gender'] ?? '',
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
        ];
    }

    /**
     * 刷新token
     */
    public function refreshToken(string $refreshToken): array
    {
        // QQ OAuth2.0不支持refresh_token，需要重新授权
        return $this->error('QQ不支持刷新token，请重新授权');
    }

    /**
     * 验证token有效性
     */
    public function verifyToken(string $accessToken, string $openid): bool
    {
        $appId = $this->getConfig('app_id');

        $params = [
            'access_token' => $accessToken,
            'oauth_consumer_key' => $appId,
            'openid' => $openid,
        ];

        $url = 'https://graph.qq.com/user/get_user_info?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        return isset($data['ret']) && $data['ret'] == 0;
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return $this->name;
    }
}
