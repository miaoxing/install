<?php

use Miaoxing\Admin\Service\AdminModel;
use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Plugin\Service\Config;
use Miaoxing\Plugin\Service\Jwt;
use Miaoxing\Plugin\Service\Seeder;
use Miaoxing\Plugin\Service\UserModel;
use Wei\Migration;
use Wei\Password;
use Wei\Schema;
use Wei\Snowflake;
use Wei\V;

return new /**
 * @mixin SchemaMixin
 * @mixin AppPropMixin
 */
class () extends BaseController {
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
        // 检查是否已安装
        $ret = Install::checkInstall();
        $this->tie($ret);

        $v = V::defaultNotEmpty();
        $v->tinyChar('dbHost', '数据库地址');
        $v->tinyChar('dbDbName', '数据库名称')->regex('/^[0-9a-z_]+$/i');
        $v->tinyChar('dbUser', '数据库用户名');
        $v->tinyChar('dbPassword', '数据库密码');
        $v->tinyChar('dbTablePrefix', '数据表前缀');
        $v->string('username', '管理员用户名', 3, 20);
        $v->string('password', '管理员密码', 6, 50);
        $v->true('agree', '《终端用户许可协议》', '请同意%name%');
        $ret = $v->check($req);
        $this->tie($ret);

        // 检查数据库连接
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
            $db->reconnect();
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

        $this->setAppId();

        $ret = Jwt::generateDefaultKeys();
        $this->tie($ret);

        $this->logger->info('run migrations');
        Migration::migrate();

        // 插入超级管理员
        $this->logger->info('create admin user');
        $user = UserModel::saveAttributes([
            'username' => $req['username'],
            'password' => Password::hash($req['password']),
            'isAdmin' => true,
            'adminType' => UserModel::ADMIN_TYPE_SUPER,
        ]);
        AdminModel::save(['userId' => $user->id]);

        if ($req['seed']) {
            $this->logger->info('run seeder');
            Seeder::run();
        }

        // 写入配置
        $this->logger->info('write install config');
        Config::updateLocal([
            'db' => [
                'host' => $host,
                'port' => $port,
                'dbname' => $req['dbDbName'],
                'user' => $req['dbUser'],
                'password' => $req['dbPassword'],
                'tablePrefix' => $req['dbTablePrefix'],
            ],
        ]);

        Install::writeLockFile();

        // 逐个安装插件
        // NOTE: 暂不处理安装失败的情况，因为没有安装逻辑
        foreach ($this->plugin->getAll() as $plugin) {
            $this->plugin->install($plugin->getId());
        }

        return suc('安装成功');
    }

    protected function setAppId()
    {
        $id = Snowflake::next();
        $model = $this->app->getModel();
        $model->id = $id;
        $this->app->setId($id);
        $this->app->setModel($model);
    }
};
