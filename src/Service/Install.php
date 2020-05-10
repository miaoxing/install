<?php

namespace Miaoxing\Install\Service;

use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\AppModel;
use Miaoxing\Plugin\Service\Ret;

/**
 * Install
 *
 * @mixin \RequestMixin
 * @mixin \ResponseMixin
 * @mixin \AppMixin
 * @mixin \EnvMixin
 */
class Install extends BaseService
{
    /**
     * @var string
     */
    protected $lockFile = 'data/install.lock';

    /**
     * 安装页面所在的路径
     *
     * @var string
     */
    protected $installPath = '/install';

    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->env->loadConfigFile('data/configs/install.php');

        $this->init();
    }

    protected function init()
    {
        if ($this->isInstalled()) {
            return;
        }

        // 跳转去安装页面
        if ($this->request->getPathInfo() !== $this->installPath
            && strpos($this->request->getPathInfo(), '/api') !== 0
        ) {
            $this->response->redirect($this->installPath)->send();
            return;
        }

        // 初始化安装信息
        $this->app->setNamespace('app');
        $this->app->setModel(AppModel::new([
            'plugin_ids' => [
                'app',
            ],
        ], [
            // IMPORT 当前是初始化流程，需要明确传入 wei 对象，才不会自动生成一个新的 wei 对象
            'wei' => $this->wei,
            'fields' => [
                'name',
                'plugin_ids',
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
            return err('程序已安装过，如需重新安装，请手动删除 ' . $this->lockFile);
        }
        return suc();
    }
}
