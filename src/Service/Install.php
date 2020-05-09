<?php

namespace Miaoxing\Install\Service;

use Miaoxing\Plugin\BaseService;

/**
 * Install
 */
class Install extends BaseService
{
    protected $installed = false;

    /**
     * @svc
     */
    protected function isInstalled()
    {
        return $this->installed;
    }
}
