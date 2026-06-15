<?php
declare(strict_types=1);

namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;
use bleeld\logins\Exception\AuthFailedException;

/**
 * 微博登录驱动
 * 基于OAuth2.0协议实现
 */
class WeiboDriver extends BaseDriver
{
    protected string $name = 'weibo';
    protected string $type = 'oauth2';

    public function getAuthUrl(string $state, string $redirectUri): string
    {
        $appId = $this->getConfig('app_id');
        if (!$appId) {
            throw new AuthFailedException('微博App Key未配置');
        }

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ];

        return 'https://api.weibo.com/oauth2/authorize?' . http_build_query($params);
    }

    public function getAccessToken(string $code, string $redirectUri): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('微博App Key或App Secret未配置');
        }

        $params = [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $url = 'https://api.weibo.com/oauth2/access_token';
        $response = $this->httpRequest($url, $params, 'POST');

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            return $this->error('获取access_token失败: ' . ($data['error_description'] ?? '未知错误'));
        }

        $accessToken = $data['access_token'];
        $uid = $data['uid'] ?? '';
        $expiresIn = $data['expires_in'] ?? 86400;

        // 获取用户信息
        $userInfo = $this->getUserInfo($accessToken, $uid);

        if (!$userInfo) {
            return $this->error('获取用户信息失败');
        }

        return $this->success([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
            'uid' => $uid,
            'user_info' => $userInfo,
        ]);
    }

    public function getUserInfo(string $accessToken, string $uid): ?array
    {
        $params = [
            'access_token' => $accessToken,
            'uid' => $uid,
        ];

        $url = 'https://api.weibo.com/2/users/show.json?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            return null;
        }

        return [
            'uid' => (string)$data['id'],
            'nickname' => $data['screen_name'] ?? '',
            'avatar' => $data['avatar_large'] ?? $data['profile_image_url'] ?? '',
            'gender' => $data['gender'] ?? '',
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
            'description' => $data['description'] ?? '',
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->error('微博不支持刷新token，请重新授权');
    }

    public function verifyToken(string $accessToken, string $uid): bool
    {
        $params = [
            'access_token' => $accessToken,
            'uid' => $uid,
        ];

        $url = 'https://api.weibo.com/2/account/get_uid.json?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        return isset($data['uid']);
    }

    public function getName(): string
    {
        return $this->name;
    }
}
