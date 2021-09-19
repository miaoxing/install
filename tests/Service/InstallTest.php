<?php

namespace MiaoxingTest\Install\Service;

use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\Service\App;
use Miaoxing\Plugin\Service\AppModel;
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
            'checkDirs',
            'checkExts',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->expects($this->once())
            ->method('checkDirs')
            ->willReturn([]);

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

    public function testCheckDirsErr()
    {
        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->setOption('checkDirs', [
            'invalid:/path',
        ]);

        $ret = Install::checkInstall();

        $this->assertRetErr($ret, '目录 invalid:/path 不可写');
    }

    public function testCheckDirsSuc()
    {
        $dir = 'public/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $message = sprintf('Failed to create directory "%s"', $dir);
            ($e = error_get_last()) && $message .= ': ' . $e['message'];
            throw new \RuntimeException($message);
        }

        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
            'checkExts',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->expects($this->once())
            ->method('checkExts')
            ->willReturn([]);

        $ret = Install::checkInstall();
        $this->assertRetSuc($ret);
    }

    public function testCheckExtsErr()
    {
        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
            'checkDirs',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->expects($this->once())
            ->method('checkDirs')
            ->willReturn([]);

        $file = glob('plugins/*/composer.json')[0];
        $content = json_decode(file_get_contents($file), true);
        $content['require']['ext-invalid'] = '*';
        $flags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES;
        file_put_contents($file, json_encode($content, $flags));

        $ret = Install::checkInstall();

        $this->assertRetErr($ret, '扩展 invalid 未安装');

        unset($content['require']['ext-invalid']);
        $content = str_replace('    ', '  ', json_encode($content, $flags)) . "\n";
        file_put_contents($file, $content);
    }

    public function testCheckExtsSuc()
    {
        $install = $this->getServiceMock(Install::class, [
            'isInstalled',
            'checkDirs',
        ]);

        $install->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $install->expects($this->once())
            ->method('checkDirs')
            ->willReturn([]);

        $ret = Install::checkInstall();

        $this->assertRetSuc($ret);
    }

    /**
     * 测试模拟 App 后，各项功能能正确读取
     *
     * @throws \ReflectionException
     */
    public function testInitApp()
    {
        $install = wei()->install;

        $method = new \ReflectionMethod($install, 'initApp');
        $method->setAccessible(true);
        $method->invoke($install);

        /** @var App $app */
        $app = wei()->app;
        $this->assertSame(1, $app->getId(), '能够读取到 id');

        $model = $app->getModel();
        $this->assertInstanceOf(AppModel::class, $model);

        $this->assertSame(1, $model->id, '能够读取到编号');

        $this->assertSame(['app', 'admin'], $model->pluginIds, '能够读取到预设的插件');

        $this->assertFalse($model->hasColumn('created_at'), '未预设 created_at 字段，读取不到');

        $app->setModel(null);
    }
}
