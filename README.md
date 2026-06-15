# ThinkPHP 三方登录插件

一个企业级安全的ThinkPHP三方登录插件，支持QQ、微信、微博、飞书、钉钉等主流平台。

## 特性

- ✅ **零依赖**：不引用大厂完整SDK包，自行实现OAuth2.0流程
- ✅ **企业级安全**：CSRF防护、Token加密存储、IP绑定验证
- ✅ **全系场景支持**：PC网页、移动端H5、APP授权登录
- ✅ **自动账号绑定**：智能处理三方账号与本地账号的绑定关系
- ✅ **可扩展**：策略模式+工厂模式设计，轻松扩展新平台
- ✅ **自动化安装**：Composer安装时自动创建数据表，卸载时自动删除

## 支持的登录平台

- QQ互联
- 微信开放平台
- 微博开放平台
- 飞书开放平台
- 钉钉开放平台

## 安装

### 1. Composer安装

```bash
composer require bleeld/think-logins:@dev
```

### 2. 配置登录参数

编辑 `config/logins.php`，填写各平台的App ID和App Secret：

```php
return [
    'default' => 'wechat',
    
    'qq' => [
        'app_id' => 'your_qq_app_id',
        'app_key' => 'your_qq_app_key',
    ],
    
    'wechat' => [
        'app_id' => 'your_wechat_appid',
        'app_secret' => 'your_wechat_appsecret',
    ],
    
    // ... 其他平台配置
];
```

### 3. 配置回调地址

在各平台开发者后台配置回调地址，例如：
- QQ: `http://yourdomain.com/login/callback/qq`
- 微信: `http://yourdomain.com/login/callback/wechat`

## 快速开始

### 获取授权URL

```php
use bleeld\logins\LoginService;
use bleeld\logins\Security\CsrfGuard;

// 初始化服务
LoginService::init();

// 生成state（防CSRF攻击）
$state = CsrfGuard::generateState();

// 获取授权URL
$driver = LoginService::driver('wechat');
$authUrl = $driver->getAuthUrl($state, 'http://yourdomain.com/login/callback/wechat');

// 跳转到授权页面
redirect($authUrl);
```

### 处理回调

```php
use bleeld\logins\LoginService;
use bleeld\logins\Security\CsrfGuard;
use bleeld\logins\model\ThirdPartyAccount;

public function callback()
{
    $code = input('get.code');
    $state = input('get.state');
    $driver = input('get.driver', 'wechat');
    
    // 验证state
    if (!CsrfGuard::verifyState($state)) {
        return json(['code' => 0, 'msg' => 'CSRF验证失败']);
    }
    
    // 获取用户信息
    LoginService::init();
    $loginDriver = LoginService::driver($driver);
    $result = $loginDriver->getAccessToken($code, 'http://yourdomain.com/login/callback/' . $driver);
    
    if ($result['code'] != 1) {
        return json($result);
    }
    
    // 绑定或创建用户
    $data = $result['data'];
    $openId = $data['openid'] ?? $data['uid'] ?? $data['user_id'] ?? '';
    $userInfo = $data['user_info'];
    
    // 查找是否已绑定
    $account = ThirdPartyAccount::findByDriverAndOpenId($driver, $openId);
    
    if ($account) {
        // 已绑定，直接登录
        $userId = $account->user_id;
    } else {
        // 未绑定，创建新用户或提示绑定
        $userId = $this->createUserFromThirdParty($driver, $openId, $userInfo, $data);
    }
    
    // 更新绑定信息
    ThirdPartyAccount::bindAccount($userId, $driver, $openId, $data);
    
    // 执行登录逻辑
    session('user_id', $userId);
    
    return json(['code' => 1, 'msg' => '登录成功']);
}
```

## API参考

### LoginService

```php
// 初始化服务
LoginService::init(array $config = []);

// 获取驱动实例
LoginService::driver(string $name = null): DriverInterface;

// 生成授权URL（带state）
LoginService::getAuthUrl(string $driver, string $redirectUri): array;
```

### DriverInterface

```php
// 获取授权URL
$driver->getAuthUrl(string $state, string $redirectUri): string;

// 通过code获取access_token和用户信息
$driver->getAccessToken(string $code, string $redirectUri): array;

// 获取用户信息
$driver->getUserInfo(string $accessToken, string $openId): ?array;

// 刷新token
$driver->refreshToken(string $refreshToken): array;

// 验证token有效性
$driver->verifyToken(string $accessToken, string $openId): bool;
```

### ThirdPartyAccount模型

```php
// 根据驱动和openid查找
ThirdPartyAccount::findByDriverAndOpenId(string $driver, string $openId): ?self;

// 根据用户ID查找所有绑定
ThirdPartyAccount::findByUserId(int $userId): array;

// 绑定三方账号
ThirdPartyAccount::bindAccount(int $userId, string $driver, string $openId, array $data): self;

// 解绑三方账号
ThirdPartyAccount::unbindAccount(int $userId, string $driver): bool;
```

## 安全说明

### CSRF防护

使用state参数防止跨站请求伪造攻击：

```php
use bleeld\logins\Security\CsrfGuard;

// 生成state
$state = CsrfGuard::generateState();

// 验证state
if (!CsrfGuard::verifyState($state)) {
    throw new Exception('CSRF验证失败');
}
```

### Token加密存储

敏感token使用AES-256-CBC加密存储：

```php
use bleeld\logins\Security\TokenEncryptor;

// 加密
$encrypted = TokenEncryptor::encrypt($token, $key);

// 解密
$token = TokenEncryptor::decrypt($encrypted, $key);
```

### IP绑定验证

可选的IP绑定功能，防止token被盗用：

```php
use bleeld\logins\Security\IpBinder;

// 绑定IP
IpBinder::bind($token, $ip);

// 验证IP
if (!IpBinder::verify($token, $ip)) {
    throw new Exception('IP验证失败');
}
```

## 扩展新平台

### 1. 创建驱动类

```php
namespace bleeld\logins\drivers;

use bleeld\logins\BaseDriver;

class GithubDriver extends BaseDriver
{
    protected string $name = 'github';
    
    public function getAuthUrl(string $state, string $redirectUri): string
    {
        // 实现GitHub授权URL生成
    }
    
    public function getAccessToken(string $code, string $redirectUri): array
    {
        // 实现获取access_token逻辑
    }
    
    // ... 其他方法
}
```

### 2. 注册驱动

在 `DriverFactory::$driverMap` 中添加：

```php
protected static array $driverMap = [
    // ...
    'github' => \bleeld\logins\drivers\GithubDriver::class,
];
```

### 3. 添加配置

在 `config/logins.php` 中添加GitHub配置：

```php
'github' => [
    'client_id' => '',
    'client_secret' => '',
],
```

## 常见问题

### Q: 如何切换默认登录方式？

A: 修改 `config/logins.php` 中的 `default` 配置项。

### Q: 如何实现一个用户绑定多个三方账号？

A: 使用 `ThirdPartyAccount` 模型的 `bindAccount()` 方法，系统会自动处理多平台绑定。

### Q: 如何自定义用户创建逻辑？

A: 在回调处理中，调用自己的用户创建方法，然后使用 `ThirdPartyAccount::bindAccount()` 绑定。

### Q: 数据表没有自动创建怎么办？

A: 手动执行 `vendor/bleeld/think-logins/database/install.sql` 文件中的SQL语句。

## 许可证

MIT License

## 技术支持

如有问题，请提交Issue或联系开发者。
