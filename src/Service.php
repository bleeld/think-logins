<?php
declare(strict_types=1);

namespace bleeld\logins;

use think\Service as BaseService;

/**
 * 登录服务提供者
 */
class Service extends BaseService
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册登录服务到容器
        $this->app->bind('logins', LoginService::class);
    }

    /**
     * 启动服务（自动建表）
     */
    public function boot(): void
    {
        // 自动创建数据表
        $this->createTables();
    }

    /**
     * 创建数据表
     */
    protected function createTables(): void
    {
        try {
            // 检查表是否已存在
            $exists = \think\facade\Db::query("SHOW TABLES LIKE 'system_third_party_account'");
            
            if (!empty($exists)) {
                return; // 表已存在，跳过
            }

            // 读取SQL文件
            $sqlFile = dirname(__DIR__) . '/database/install.sql';
            if (!file_exists($sqlFile)) {
                return;
            }

            $sql = file_get_contents($sqlFile);
            if (empty($sql)) {
                return;
            }

            // 执行SQL
            \think\facade\Db::execute($sql);

            // 记录日志
            if (function_exists('trace')) {
                trace('三方登录插件数据表创建成功', 'info');
            }
        } catch (\Exception $e) {
            // 记录错误日志
            if (function_exists('trace')) {
                trace('三方登录插件数据表创建失败: ' . $e->getMessage(), 'error');
            }
        }
    }
}
