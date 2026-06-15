<?php
declare(strict_types=1);

namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;
use bleeld\logins\Exception\AuthFailedException;

/**
 * 钉钉登录驱动
 * 基于OAuth2.0协议实现
 */
class DingtalkDriver extends BaseDriver
{
    protected string $name = 'dingtalk';
    protected string $type = 'oauth2';

    public function getAuthUrl(string $state, string $redirectUri): string
    {
        $appId = $this->getConfig('app_id');
        if (!$appId) {
            throw new AuthFailedException('钉钉App ID未配置');
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $appId,
            'scope' => 'openid',
            'state' => $state,
            'redirect_uri' => urlencode($redirectUri),
            'prompt' => 'consent',
        ];

        return 'https://login.dingtalk.com/oauth2/auth?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('钉钉App ID或App Secret未配置');
        }

        // 第一步：获取access_token
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $appId,
            'client_secret' => $appSecret,
        ];

        $tokenUrl = 'https://api.dingtalk.com/v1.0/oauth2/userAccessToken';
        $response = $this->httpRequest($tokenUrl, json_encode($tokenParams), 'POST', [
            'Content-Type: application/json',
        ]);

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['accessToken'])) {
            return $this->error('获取access_token失败: ' . ($tokenData['message'] ?? '未知错误'));
        }

        $accessToken = $tokenData['accessToken'];
        $refreshToken = $tokenData['refreshToken'] ?? '';
        $expiresIn = $tokenData['expireIn'] ?? 7200;

        // 第二步：获取用户信息
        $userInfo = $this->getUserInfo($accessToken);

        if (!$userInfo) {
            return $this->error('获取用户信息失败');
        }

        return $this->success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'union_id' => $userInfo['unionid'] ?? '',
            'user_info' => $userInfo,
        ]);
    }

    public function getUserInfo(string $accessToken): ?array
    {
        $url = 'https://api.dingtalk.com/v1.0/contact/users/me';
        $response = $this->httpRequest($url, null, 'GET', [
            'x-acs-dingtalk-access-token: ' . $accessToken,
        ]);

        $data = json_decode($response, true);

        if (!isset($data['unionId'])) {
            return null;
        }

        return [
            'unionid' => $data['unionId'] ?? '',
            'openid' => $data['openId'] ?? '',
            'nickname' => $data['nick'] ?? '',
            'avatar' => $data['avatarUrl'] ?? '',
            'mobile' => $data['mobile'] ?? '',
            'email' => $data['email'] ?? '',
            'job_number' => $data['jobNumber'] ?? '',
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('钉钉App ID或App Secret未配置');
        }

        $params = [
            'grant_type' => 'refresh_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'refresh_token' => $refreshToken,
        ];

        $url = 'https://api.dingtalk.com/v1.0/oauth2/refreshUserAccessToken';
        $response = $this->httpRequest($url, json_encode($params), 'POST', [
            'Content-Type: application/json',
        ]);

        $data = json_decode($response, true);

        if (!isset($data['accessToken'])) {
            return $this->error('刷新token失败: ' . ($data['message'] ?? '未知错误'));
        }

        return $this->success([
            'access_token' => $data['accessToken'],
            'refresh_token' => $data['refreshToken'],
            'expires_in' => $data['expireIn'] ?? 7200,
        ]);
    }

    public function verifyToken(string $accessToken, string $userId): bool
    {
        $url = 'https://api.dingtalk.com/v1.0/contact/users/me';
        $response = $this->httpRequest($url, null, 'GET', [
            'x-acs-dingtalk-access-token: ' . $accessToken,
        ]);

        $data = json_decode($response, true);

        return isset($data['unionId']);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
