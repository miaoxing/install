<?php

namespace MiaoxingTest\Install\Service;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\Test\BaseTestCase;

/**
 * @internal
 */
final class InstallTest extends BaseTestCase
{
    public function testLockFile()
    {
        $file = Install::getLockFile();
        $this->assertIsString(Install::getLockFile());

        $installed = Install::isInstalled();
        if ($installed) {
            unlink($file);
        }

        $this->assertFalse(Install::isInstalled());
        Install::writeLockFile();
        $this->assertTrue(Install::isInstalled());

        if (!$installed) {
            unlink($file);
        }
    }

    public function testCheckInstallSuc()
    {
        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
            'checkStorageDir',
            'checkExts',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->expects($this->once())
            ->method('checkStorageDir')
            ->willReturn(suc());

        $install->expects($this->once())
            ->method('checkExts')
            ->willReturn([]);

        $ret = Install::checkInstall();

        $this->assertRetSuc($ret);
        $this->assertRetSuc($ret->getData()[0]);
    }

    public function testCheckInstallErr()
    {
        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(true);

        $ret = Install::checkInstall();
        $this->assertRetErr($ret, '程序已安装过，如需重新安装，请手动删除：storage/install.lock.php');
    }
}
