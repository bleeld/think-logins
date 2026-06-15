<?php
declare(strict_types=1);

namespace bleeld\logins\model;

use think\Model;

/**
 * 三方账号绑定模型
 */
class ThirdPartyAccount extends Model
{
    // 设置数据表名
    protected $name = 'third_party_account';
    
    // 设置主键
    protected $pk = 'id';
    
    // 自动时间戳
    protected $autoWriteTimestamp = true;
    
    // 时间字段格式
    protected $dateFormat = false;
    
    // 字段类型转换
    protected $type = [
        'user_id' => 'integer',
        'token_expires_at' => 'integer',
        'last_login_time' => 'integer',
        'create_time' => 'integer',
        'update_time' => 'integer',
    ];

    /**
     * 关联本地用户
     */
    public function user()
    {
        return $this->belongsTo('app\common\model\User', 'user_id', 'id');
    }

    /**
     * 根据驱动和openid查找记录
     */
    public static function findByDriverAndOpenId(string $driver, string $openId): ?self
    {
        return self::where('driver', $driver)
            ->where('open_id', $openId)
            ->find();
    }

    /**
     * 根据用户ID查找所有绑定的三方账号
     */
    public static function findByUserId(int $userId): array
    {
        return self::where('user_id', $userId)->select()->toArray();
    }

    /**
     * 绑定三方账号到用户
     */
    public static function bindAccount(int $userId, string $driver, string $openId, array $data): self
    {
        $account = self::findByDriverAndOpenId($driver, $openId);
        
        if ($account) {
            // 更新已有绑定
            $account->user_id = $userId;
            $account->access_token = $data['access_token'] ?? '';
            $account->refresh_token = $data['refresh_token'] ?? '';
            $account->token_expires_at = time() + ($data['expires_in'] ?? 7200);
            $account->user_info = json_encode($data['user_info'] ?? [], JSON_UNESCAPED_UNICODE);
            $account->last_login_time = time();
            $account->last_login_ip = request()->ip();
            $account->save();
        } else {
            // 创建新绑定
            $account = new self();
            $account->user_id = $userId;
            $account->driver = $driver;
            $account->open_id = $openId;
            $account->union_id = $data['unionid'] ?? $data['union_id'] ?? '';
            $account->access_token = $data['access_token'] ?? '';
            $account->refresh_token = $data['refresh_token'] ?? '';
            $account->token_expires_at = time() + ($data['expires_in'] ?? 7200);
            $account->user_info = json_encode($data['user_info'] ?? [], JSON_UNESCAPED_UNICODE);
            $account->last_login_time = time();
            $account->last_login_ip = request()->ip();
            $account->create_time = time();
            $account->update_time = time();
            $account->save();
        }
        
        return $account;
    }

    /**
     * 解绑三方账号
     */
    public static function unbindAccount(int $userId, string $driver): bool
    {
        $account = self::where('user_id', $userId)
            ->where('driver', $driver)
            ->find();
        
        if ($account) {
            return $account->delete() !== false;
        }
        
        return false;
    }
}
