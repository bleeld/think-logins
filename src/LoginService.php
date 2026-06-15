<?php
declare(strict_types=1);

namespace bleeld\logins;

use bleeld\logins\Exception\ConfigException;
use bleeld\logins\Security\CsrfGuard;

/**
 * 登录服务类
 * 提供统一的三方登录接口
 */
class LoginService
{
    /**
     * 配置数据
     */
    protected static array $config = [];

    /**
     * 默认驱动名称
     */
    protected static string $defaultDriver = '';

    /**
     * 初始化服务
     */
    public static function init(array $config = []): void
    {
        if (empty($config)) {
            // 尝试从ThinkPHP配置加载
            if (function_exists('config')) {
                $config = config('logins', []);
            }
        }

        if (empty($config)) {
            throw new ConfigException('登录配置不能为空');
        }

        self::$config = $config;
        self::$defaultDriver = $config['default'] ?? 'wechat';
    }

    /**
     * 获取配置
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * 设置配置
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$defaultDriver = $config['default'] ?? 'wechat';
        DriverFactory::clearCache();
    }

    /**
     * 获取驱动实例
     */
    public static function driver(?string $name = null): DriverInterface
    {
        if (empty(self::$config)) {
            self::init();
        }

        $driverName = $name ?? self::$defaultDriver;
        
        if (!isset(self::$config[$driverName])) {
            throw new ConfigException("登录驱动 [{$driverName}] 配置不存在");
        }

        return DriverFactory::make($driverName, self::$config[$driverName]);
    }

    /**
     * 获取授权URL
     */
    public static function getAuthUrl(string $driver, string $redirectUri): string
    {
        if (empty(self::$config)) {
            self::init();
        }

        $state = CsrfGuard::generateState();
        
        return self::driver($driver)->getAuthUrl($state, $redirectUri);
    }

    /**
     * 处理回调
     */
    public static function handleCallback(string $driver, string $code, string $state, string $redirectUri): array
    {
        if (empty(self::$config)) {
            self::init();
        }

        $loginDriver = self::driver($driver);
        
        // 验证state
        if (!$loginDriver->verifyState($state)) {
            return ['code' => 0, 'msg' => 'state验证失败，可能是CSRF攻击'];
        }

        // 获取access_token
        $tokenResult = $loginDriver->getAccessToken($code, $redirectUri);
        
        if ($tokenResult['code'] !== 1) {
            return $tokenResult;
        }

        // 获取用户信息
        $userInfoResult = $loginDriver->getUserInfo($tokenResult['data']['access_token']);
        
        if ($userInfoResult['code'] !== 1) {
            return $userInfoResult;
        }

        // 合并数据
        $userInfoResult['data'] = array_merge($tokenResult['data'], $userInfoResult['data']);
        
        return $userInfoResult;
    }

    /**
     * 通过三方ID查找本地用户
     */
    public static function findUserByThirdParty(string $driver, string $openId): ?array
    {
        if (!class_exists('\\app\\common\\model\\ThirdPartyAccount')) {
            return null;
        }

        $account = \app\common\model\ThirdPartyAccount::where('driver', $driver)
            ->where('open_id', $openId)
            ->where('status', 1)
            ->find();

        if (!$account) {
            return null;
        }

        // 更新最后登录信息
        $account->last_login_time = date('Y-m-d H:i:s');
        $account->last_login_ip = request()->ip() ?? '';
        $account->save();

        // 返回用户信息
        if ($account->user_id && class_exists('\\app\\common\\model\\User')) {
            return \app\common\model\User::find($account->user_id)?->toArray();
        }

        return null;
    }

    /**
     * 绑定账号
     */
    public static function bindAccount(int $userId, string $driver, array $thirdPartyData): bool
    {
        if (!class_exists('\\app\\common\\model\\ThirdPartyAccount')) {
            return false;
        }

        try {
            $account = new \app\common\model\ThirdPartyAccount();
            $account->user_id = $userId;
            $account->driver = $driver;
            $account->open_id = $thirdPartyData['open_id'] ?? '';
            $account->union_id = $thirdPartyData['union_id'] ?? '';
            $account->nickname = $thirdPartyData['nickname'] ?? '';
            $account->avatar = $thirdPartyData['avatar'] ?? '';
            $account->gender = $thirdPartyData['gender'] ?? '';
            $account->extra_data = json_encode($thirdPartyData, JSON_UNESCAPED_UNICODE);
            $account->last_login_time = date('Y-m-d H:i:s');
            $account->last_login_ip = request()->ip() ?? '';
            $account->status = 1;
            $account->create_time = date('Y-m-d H:i:s');
            
            return $account->save();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 解绑账号
     */
    public static function unbindAccount(int $userId, string $driver): bool
    {
        if (!class_exists('\\app\\common\\model\\ThirdPartyAccount')) {
            return false;
        }

        try {
            return \app\common\model\ThirdPartyAccount::where('user_id', $userId)
                ->where('driver', $driver)
                ->delete() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取用户绑定的所有三方账号
     */
    public static function getBoundAccounts(int $userId): array
    {
        if (!class_exists('\\app\\common\\model\\ThirdPartyAccount')) {
            return [];
        }

        $accounts = \app\common\model\ThirdPartyAccount::where('user_id', $userId)
            ->where('status', 1)
            ->select();

        return $accounts ? $accounts->toArray() : [];
    }

    /**
     * 注册新驱动
     */
    public static function registerDriver(string $name, string $class): void
    {
        DriverFactory::register($name, $class);
    }

    /**
     * 切换默认驱动
     */
    public static function use(string $driverName): self
    {
        self::$defaultDriver = $driverName;
        return new self();
    }

    /**
     * 检查驱动是否已注册
     */
    public static function hasDriver(string $name): bool
    {
        return DriverFactory::has($name);
    }

    /**
     * 获取所有驱动列表
     */
    public static function getDrivers(): array
    {
        return DriverFactory::getDrivers();
    }
}
