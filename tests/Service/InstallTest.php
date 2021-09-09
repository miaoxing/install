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
}
