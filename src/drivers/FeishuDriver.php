<?php
declare(strict_types=1);

namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;
use bleeld\logins\Exception\AuthFailedException;

/**
 * 飞书登录驱动
 * 基于OAuth2.0协议实现
 */
class FeishuDriver extends BaseDriver
{
    protected string $name = 'feishu';
    protected string $type = 'oauth2';

    public function getAuthUrl(string $state, string $redirectUri): string
    {
        $appId = $this->getConfig('app_id');
        if (!$appId) {
            throw new AuthFailedException('飞书App ID未配置');
        }

        $params = [
            'app_id' => $appId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        return 'https://open.feishu.cn/open-apis/authen/v1/index?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('飞书App ID或App Secret未配置');
        }

        // 第一步：获取tenant_access_token
        $tokenParams = [
            'app_id' => $appId,
            'app_secret' => $appSecret,
        ];

        $tokenUrl = 'https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal';
        $response = $this->httpRequest($tokenUrl, json_encode($tokenParams), 'POST', [
            'Content-Type: application/json',
        ]);

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['tenant_access_token'])) {
            return $this->error('获取tenant_access_token失败: ' . ($tokenData['msg'] ?? '未知错误'));
        }

        // 第二步：通过code获取user_access_token
        $userTokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
        ];

        $userTokenUrl = 'https://open.feishu.cn/open-apis/authen/v1/access_token';
        $userTokenResponse = $this->httpRequest(
            $userTokenUrl,
            json_encode($userTokenParams),
            'POST',
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $tokenData['tenant_access_token'],
            ]
        );

        $userTokenData = json_decode($userTokenResponse, true);

        if (!isset($userTokenData['data']['access_token'])) {
            return $this->error('获取user_access_token失败: ' . ($userTokenData['msg'] ?? '未知错误'));
        }

        $accessToken = $userTokenData['data']['access_token'];
        $refreshToken = $userTokenData['data']['refresh_token'] ?? '';
        $expiresIn = $userTokenData['data']['expires_in'] ?? 7200;
        $userId = $userTokenData['data']['user_id'] ?? '';

        // 第三步：获取用户信息
        $userInfo = $this->getUserInfo($accessToken);

        if (!$userInfo) {
            return $this->error('获取用户信息失败');
        }

        return $this->success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'user_id' => $userId,
            'user_info' => $userInfo,
        ]);
    }

    public function getUserInfo(string $accessToken): ?array
    {
        $url = 'https://open.feishu.cn/open-apis/authen/v1/user_info';
        $response = $this->httpRequest($url, null, 'GET', [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $data = json_decode($response, true);

        if (!isset($data['data'])) {
            return null;
        }

        $userData = $data['data'];

        return [
            'user_id' => $userData['user_id'] ?? '',
            'union_id' => $userData['union_id'] ?? '',
            'open_id' => $userData['open_id'] ?? '',
            'nickname' => $userData['name'] ?? '',
            'avatar' => $userData['avatar_url'] ?? '',
            'email' => $userData['email'] ?? '',
            'mobile' => $userData['mobile'] ?? '',
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('飞书App ID或App Secret未配置');
        }

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $url = 'https://open.feishu.cn/open-apis/authen/v1/refresh_access_token';
        $response = $this->httpRequest($url, json_encode($params), 'POST', [
            'Content-Type: application/json',
        ]);

        $data = json_decode($response, true);

        if (!isset($data['data']['access_token'])) {
            return $this->error('刷新token失败: ' . ($data['msg'] ?? '未知错误'));
        }

        return $this->success([
            'access_token' => $data['data']['access_token'],
            'refresh_token' => $data['data']['refresh_token'],
            'expires_in' => $data['data']['expires_in'] ?? 7200,
        ]);
    }

    public function verifyToken(string $accessToken, string $userId): bool
    {
        $url = 'https://open.feishu.cn/open-apis/authen/v1/user_info';
        $response = $this->httpRequest($url, null, 'GET', [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $data = json_decode($response, true);

        return isset($data['code']) && $data['code'] == 0;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
