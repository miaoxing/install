<?php

namespace Miaoxing\Install\Service;

use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\AppModel;
use Miaoxing\Plugin\Service\Ret;

/**
 * Install
 *
 * @mixin \ReqMixin
 * @mixin \ResMixin
 * @mixin \AppMixin
 * @mixin \EnvMixin
 * @mixin \UrlMixin
 */
class Install extends BaseService
{
    /**
     * @var string
     */
    protected $lockFile = 'storage/install.lock';

    /**
     * 安装页面所在的路径
     *
     * @var string
     */
    protected $installUrl = 'admin/install';

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->init();
    }

    protected function init()
    {
        if (\PHP_SAPI === 'cli' || $this->isInstalled()) {
            return;
        }

        // 跳转去安装页面
        $url = $this->url->to($this->installUrl);
        if (
            $this->req->getRequestUri() !== $url
            && 0 !== strpos($this->req->getRouterPathInfo(), '/admin-api')
        ) {
            $this->res->redirect($url)->send();
            $this->exit();
            return;
        }

        // 初始化安装信息
        $this->app->setId(1);
        $this->app->setModel(AppModel::new([
            'id' => 1,
            'name' => 'app',
            'pluginIds' => [
                'app',
            ],
        ], [
            // IMPORT 当前是初始化流程，需要明确传入 wei 对象，才不会自动生成一个新的 wei 对象
            'wei' => $this->wei,
            'db' => $this->db,
            'loadedColumns' => true,
            'columns' => [
                'id' => [],
                'name' => [],
                'pluginIds' => [
                    'cast' => 'list',
                ],
            ],
        ]));
    }

    /**
     * @svc
     */
    protected function isInstalled()
    {
        return file_exists($this->lockFile);
    }

    /**
     * @return Ret
     * @svc
     */
    protected function checkInstall()
    {
        if ($this->isInstalled()) {
            return err(['程序已安装过，如需重新安装，请手动删除：%s', $this->lockFile]);
        }
        return suc();
    }

    /**
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    protected function exit()
    {
        exit;
    }
}
