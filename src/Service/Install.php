<?php

namespace Miaoxing\Install\Service;

use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\Ret;

/**
 * Install
 */
class Install extends BaseService
{
    protected $lockFile = 'data/install.lock';

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
