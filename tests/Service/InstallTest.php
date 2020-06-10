<?php

namespace MiaoxingTest\Install\Service;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\Test\BaseTestCase;

class InstallTest extends BaseTestCase
{
    public function testIsInstalled()
    {
        $this->assertFalse(Install::isInstalled());
    }
}