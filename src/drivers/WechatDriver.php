<?php
declare(strict_types=1);

namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;
use bleeld\logins\Exception\AuthFailedException;

/**
 * 微信登录驱动
 * 支持网页授权和扫码登录
 */
class WechatDriver extends BaseDriver
{
    protected string $name = 'wechat';
    protected string $type = 'oauth2';

    /**
     * 获取授权URL（网页授权）
     */
    public function getAuthUrl(string $state, string $redirectUri): string
    {
        $appId = $this->getConfig('app_id');
        if (!$appId) {
            throw new AuthFailedException('微信App ID未配置');
        }

        $params = [
            'appid' => $appId,
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'scope' => $this->getConfig('scope', 'snsapi_userinfo'),
            'state' => $state . '#wechat_redirect',
        ];

        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query($params);
    }

    /**
     * 通过code获取access_token和用户信息
     */
    public function getAccessToken(string $code, string $redirectUri): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('微信App ID或App Secret未配置');
        }

        // 第一步：获取access_token和openid
        $tokenParams = [
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $tokenUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query($tokenParams);
        $response = $this->httpRequest($tokenUrl, null, 'GET');

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            return $this->error('获取access_token失败: ' . ($tokenData['errmsg'] ?? '未知错误'));
        }

        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? '';
        $expiresIn = $tokenData['expires_in'] ?? 7200;
        $openid = $tokenData['openid'] ?? '';
        $unionId = $tokenData['unionid'] ?? '';

        // 第二步：获取用户信息
        $userInfo = $this->getUserInfo($accessToken, $openid);

        if (!$userInfo) {
            return $this->error('获取用户信息失败');
        }

        // 如果有unionid，添加到用户信息中
        if ($unionId) {
            $userInfo['unionid'] = $unionId;
        }

        return $this->success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $expiresIn,
            'openid' => $openid,
            'unionid' => $unionId,
            'user_info' => $userInfo,
        ]);
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(string $accessToken, string $openid): ?array
    {
        $params = [
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN',
        ];

        $url = 'https://api.weixin.qq.com/sns/userinfo?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        if (!isset($data['openid'])) {
            return null;
        }

        return [
            'openid' => $data['openid'],
            'nickname' => $data['nickname'] ?? '',
            'avatar' => $data['headimgurl'] ?? '',
            'gender' => $data['sex'] ?? 0,
            'province' => $data['province'] ?? '',
            'city' => $data['city'] ?? '',
            'country' => $data['country'] ?? '',
            'language' => $data['language'] ?? 'zh_CN',
        ];
    }

    /**
     * 刷新token
     */
    public function refreshToken(string $refreshToken): array
    {
        $appId = $this->getConfig('app_id');
        $appSecret = $this->getConfig('app_secret');

        if (!$appId || !$appSecret) {
            return $this->error('微信App ID或App Secret未配置');
        }

        $params = [
            'appid' => $appId,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        $url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            return $this->error('刷新token失败: ' . ($data['errmsg'] ?? '未知错误'));
        }

        return $this->success([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_in' => $data['expires_in'] ?? 7200,
            'openid' => $data['openid'],
        ]);
    }

    /**
     * 验证token有效性
     */
    public function verifyToken(string $accessToken, string $openid): bool
    {
        $params = [
            'access_token' => $accessToken,
            'openid' => $openid,
        ];

        $url = 'https://api.weixin.qq.com/sns/auth?' . http_build_query($params);
        $response = $this->httpRequest($url, null, 'GET');

        $data = json_decode($response, true);

        return isset($data['errcode']) && $data['errcode'] == 0;
    }

    /**
     * 获取驱动名称
     */
    public function getName(): string
    {
        return $this->name;
    }
}
