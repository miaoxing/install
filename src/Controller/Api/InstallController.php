<?php

namespace Miaoxing\Install\Controller\Api;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\BaseController;
use Miaoxing\Services\Service\Url;
use Miaoxing\Services\Service\V;

class InstallController extends BaseController
{
    public function installedAction()
    {
        $installed = Install::isInstalled();
        if ($installed) {
            return err([
                'message' => '程序已安装过，如需重新安装，请手动删除 install.lock',
                'next' => Url::to('admin'),
            ]);
        }

        return suc();
    }

    public function createAction($req)
    {
        $ret = V::key('dbHost', '数据库地址')
            ->key('dbName', '数据库名称')
            ->key('dbUsername', '数据库用户名')
            ->key('dbPassword', '数据库密码')
            ->key('dbTablePrefix', '数据表前缀')
            ->key('username', '管理员用户名')
            ->key('password', '管理员密码')
            ->key('agree', '《服务协议》')->required()->message('请同意%name%')
            ->check($req);
        $this->tie($ret);

        return suc();
    }
}
