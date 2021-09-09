<?php

use Miaoxing\Admin\Service\AdminModel;
use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Plugin\Service\Config;
use Miaoxing\Plugin\Service\Jwt;
use Miaoxing\Plugin\Service\Seeder;
use Miaoxing\Plugin\Service\UserModel;
use Miaoxing\Services\Service\Url;
use Wei\Migration;
use Wei\Password;
use Wei\Schema;
use Wei\V;

return new
/**
 * @mixin SchemaMixin
 */
class extends BaseController {
    protected $controllerAuth = false;

    public function get()
    {
        return suc([
            'data' => [
                'installRet' => Install::checkInstall(),
                'license' => file_get_contents('LICENSE.txt'),
            ],
        ]);
    }

    public function post($req)
    {
        // 1. 检查是否已安装
        $ret = Install::checkInstall();
        $this->tie($ret);

        $ret = V::key('dbHost', '数据库地址')
            ->key('dbDbName', '数据库名称')->regex('/^[0-9a-z_]+$/i')
            ->key('dbUser', '数据库用户名')
            ->key('dbPassword', '数据库密码')
            ->key('dbTablePrefix', '数据表前缀')
            ->key('username', '管理员用户名')
            ->key('password', '管理员密码')
            ->key('agree', '《终端用户许可协议》')->required()->message('请同意%name%')
            ->check($req);
        $this->tie($ret);

        // 2. 检查数据库连接
        if (false !== strpos($req['dbHost'], ':')) {
            [$host, $port] = explode(':', $req['dbHost']);
        } else {
            $host = $req['dbHost'];
            $port = null;
        }

        $db = wei()->db;
        $db->setOption([
            'host' => $host,
            'port' => $port,
            'user' => $req['dbUser'],
            // NOTE: 不指定数据库，判断不存在则新建
            'dbname' => '',
            'password' => $req['dbPassword'],
            'tablePrefix' => $req['dbTablePrefix'],
        ]);
        try {
            $db->connect();
        } catch (\Exception $e) {
            return err(['连接数据库失败：%s', $e->getMessage()]);
        }

        // 如果数据库不存在，尝试自动创建
        if (!Schema::hasDatabase($req['dbDbName'])) {
            try {
                Schema::createDatabase($req['dbDbName']);
            } catch (\Exception $e) {
                return err(['创建数据库失败，请手动创建：%s', $e->getMessage()]);
            }
        }
        $db->useDb($req['dbDbName']);

        // 避免直接删除 install.lock 后重新安装错误
        if ($this->schema->hasTable('migrations')) {
            return err(['数据表 %s 已存在，不能安装', $db->getTable('migrations')]);
        }

        $ret = Jwt::generateDefaultKeys();
        $this->tie($ret);

        $this->logger->info('run migrations');
        Migration::migrate();

        // 插入默认管理员
        $this->logger->info('create admin user');
        $user = UserModel::saveAttributes([
            'username' => $req['username'],
            'password' => Password::hash($req['password']),
            'isAdmin' => true,
        ]);
        AdminModel::save(['userId' => $user->id]);

        if ($req['seed']) {
            $this->logger->info('run seeder');
            Seeder::run();
        }

        // 3. 逐个安装插件
        $rets = [];
        foreach ($this->plugin->getAll() as $plugin) {
            $ret = $this->plugin->install($plugin->getId());
            if (1 !== $ret['code']) {
                $rets[] = $ret;
            }
        }

        // 写入配置
        $this->logger->info('write install config');
        Config::save([
            'db' => [
                'host' => $host,
                'port' => $port,
                'dbname' => $req['dbDbName'],
                'user' => $req['dbUser'],
                'password' => $req['dbPassword'],
                'tablePrefix' => $req['dbTablePrefix'],
            ],
            'request' => [
                'defaultUrlRewrite' => $req['requestDefaultUrlRewrite'],
            ],
        ]);
        Config::load();

        Install::writeLockFile();

        return suc([
            'message' => '安装成功',
            'next' => Url::to('admin/login'),
        ]);
    }
};
