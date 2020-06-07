<?php

namespace Miaoxing\Install\Controller\Api;

use Illuminate\Support\Facades\Log;
use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Plugin\Service\Config;
use Miaoxing\Plugin\Service\UserModel;
use Miaoxing\Services\Service\Migration;
use Miaoxing\Services\Service\Time;
use Miaoxing\Services\Service\Url;
use Miaoxing\Services\Service\V;
use Wei\Password;

/**
 * @mixin \SchemaMixin
 */
class InstallController extends BaseController
{
    protected $controllerAuth = false;

    public function installedAction()
    {
        return Install::checkInstall();
    }

    public function licenseAction()
    {
        return suc([
            'content' => file_get_contents('LICENSE.txt'),
        ]);
    }

    public function createAction($req)
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
        if (strpos($req['dbHost'], ':') !== false) {
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
            return err('连接数据库失败：' . $e->getMessage());
        }

        // 如果数据库不存在，尝试自动创建
        $databases = array_column($db->fetchAll('SHOW DATABASES'), 'Database');
        if (!in_array($req['dbDbName'], $databases)) {
            try {
                $db->executeUpdate('CREATE DATABASE IF NOT EXISTS ' . $req['dbDbName']);
            } catch (\Exception $e) {
                return err('创建数据库失败，请手动创建：' . $e->getMessage());
            }
        }
        $db->useDb($req['dbDbName']);

        // 避免直接删除 install.lock 后重新安装错误
        if ($this->schema->hasTable('migrations')) {
            return err(['数据表 %s 已存在，不能安装', $db->getTable('migrations')]);
        }

        // 运行
        Migration::migrate();

        // 插入默认管理员
        UserModel::save([
            'username' => $req['username'],
            'password' => Password::hash($req['password']),
            'isAdmin' => true,
        ]);

        // 3. 逐个安装插件
        $rets = [];
        foreach ($this->plugin->getAll() as $plugin) {
            $ret = $this->plugin->install($plugin->getId());
            if ($ret['code'] !== 1) {
                $rets[] = $ret;
            }
        }

        // 写入配置
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
        file_put_contents('storage/install.lock', Time::now());

        return suc([
            'message' => '安装成功',
            'next' => Url::to('admin/login'),
        ]);
    }
}
