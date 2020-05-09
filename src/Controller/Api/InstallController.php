<?php

namespace Miaoxing\Install\Controller\Api;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Plugin\Service\UserModel;
use Miaoxing\Services\Service\Migration;
use Miaoxing\Services\Service\Time;
use Miaoxing\Services\Service\Url;
use Miaoxing\Services\Service\V;
use Wei\Db;
use Wei\Password;

class InstallController extends BaseController
{
    public function installedAction()
    {
        return Install::checkInstall();
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
            ->key('agree', '《服务协议》')->required()->message('请同意%name%')
            ->check($req);
        $this->tie($ret);

        // 2. 检查数据库连接
        if (strpos($req['dbHost'], ':') !== false) {
            [$host, $port] = explode(':', $req['dbHost']);
        } else {
            $host = $req['dbHost'];
            $port = null;
        }
        // NOTE: 不指定数据库，判断不存在则新建
        $db = new Db([
            'host' => $host,
            'port' => $port,
            'user' => $req['dbUser'],
            'charset' => 'utf8mb4',
            'password' => $req['dbPassword'],
            'tablePrefix' => $req['dbTablePrefix'],
        ]);
        try {
            $db->connect();
        } catch (\Exception $e) {
            return err('连接数据库失败：' . $e->getMessage());
        }

        // 如果数据库不存在，尝试自动创建
        $databases = array_column($db->fetchAll("SHOW DATABASES"), 'Database');
        if (!in_array($req['dbDbName'], $databases)) {
            try {
                $db->executeUpdate('CREATE DATABASE IF NOT EXISTS ' . $req['dbDbName']);
            } catch (\Exception $e) {
                return err('创建数据库失败，请手动创建：' . $e->getMessage());
            }
        }
        $db->useDb($req['dbDbName']);
        $this->wei->db = $db;

        // 运行
        Migration::migrate();

        // 插入默认管理员
        UserModel::save([
            'username' => $req['username'],
            'is_admin' => true,
            'password' => Password::hash($req['password']),
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
        file_put_contents('data/configs/install.php', "<?php\n\nreturn " . var_export([
                'db' => [
                    'host' => $host,
                    'port' => $port,
                    'dbname' => $req['dbDbName'],
                    'charset' => 'utf8mb4',
                    'password' => $req['dbPassword'],
                    'tablePrefix' => $req['dbTablePrefix'],
                ],
            ], true) . ';');
        file_put_contents('data/install.lock', Time::now());

        return suc([
            'message' => '安装成功',
            'next' => Url::to('admin'),
        ]);
    }
}
