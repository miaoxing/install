<?php

namespace MiaoxingTest\Install\Service;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\Test\BaseTestCase;

/**
 * @internal
 */
final class InstallTest extends BaseTestCase
{
    public function testIsInstalled()
    {
        $this->assertFalse(Install::isInstalled());
    }
}
