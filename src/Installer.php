<?php
declare(strict_types=1);

namespace bleeld\logins;

/**
 * 安装器类
 * 处理插件卸载时的清理工作
 */
class Installer
{
    /**
     * 卸载插件（删除数据表和配置）
     */
    public static function uninstall(): void
    {
        try {
            // 读取卸载SQL文件
            $sqlFile = dirname(__DIR__) . '/database/uninstall.sql';
            
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                
                if (!empty($sql)) {
                    // 执行SQL
                    \think\facade\Db::execute($sql);
                }
            }

            // 删除配置文件
            $configFile = config_path() . 'logins.php';
            if (file_exists($configFile)) {
                @unlink($configFile);
            }

            // 记录日志
            if (function_exists('trace')) {
                trace('三方登录插件卸载成功', 'info');
            }
        } catch (\Exception $e) {
            // 记录错误日志
            if (function_exists('trace')) {
                trace('三方登录插件卸载失败: ' . $e->getMessage(), 'error');
            }
        }
    }
}
