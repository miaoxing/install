<?php

namespace MiaoxingTest\Install\Pages\Api\Admin\Install;

use Miaoxing\Admin\Service\AdminModel;
use Miaoxing\Install\Service\Install;
use Miaoxing\Plugin\Service\Config;
use Miaoxing\Plugin\Service\Seeder;
use Miaoxing\Plugin\Service\Tester;
use Miaoxing\Plugin\Service\UserModel;
use Miaoxing\Plugin\Test\BaseTestCase;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use Wei\Db;
use Wei\Migration;
use Wei\Ret;
use Wei\Schema;

class IndexTest extends BaseTestCase
{
    /**
     * @var string
     */
    protected $dbname;

    /**
     * @var string
     */
    protected $port;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbname = $this->wei->db->getDbname();
        $this->port = $this->wei->db->getPort();
    }

    protected function tearDown(): void
    {
        // 安装时会清空
        $this->wei->db->setOption('dbname', $this->dbname);
        $this->wei->db->setOption('port', $this->port);
        parent::tearDown();
    }

    public function testGet()
    {
        /** @var Ret $ret */
        $ret = Tester::getAdminApi('install');
        $this->assertRetSuc($ret);

        $this->assertArrayHasKey('installRet', $ret->getData());
        $this->assertArrayHasKey('license', $ret->getData());
    }

    public function testPost()
    {
        $this->getInstallMock();

        $schema = $this->getServiceMock(Schema::class, ['hasTable']);
        // 前两次是下面的 Migration 和 Seeder mock 时触发
        $schema->expects($this->exactly(3))
            ->method('hasTable')
            ->withConsecutive(['migrations'], ['seeders'], ['migrations'])
            ->willReturnOnConsecutiveCalls(true, true, false);

        $migration = $this->getServiceMock(Migration::class, ['migrate']);
        $migration->expects($this->once())
            ->method('migrate');

        $seeder = $this->getServiceMock(Seeder::class, ['run']);
        $seeder->expects($this->once())
            ->method('run');

        $user = $this->getModelServiceMock(UserModel::class, ['saveAttributes']);
        $user->expects($this->once())
            ->method('saveAttributes')
            ->willReturn($user);

        $admin = $this->getModelServiceMock(AdminModel::class, ['save']);
        $admin->expects($this->once())
            ->method('save');

        $config = $this->getServiceMock(Config::class, [
            'updateLocal',
        ]);
        $config->expects($this->once())
            ->method('updateLocal');

        $db = wei()->db;
        $ret = Tester::postAdminApi('install', [
            // 使用已有的值，不用 mock，mock需处理非常多调用
            'dbHost' => $this->buildHostAndPort($db),
            'dbDbName' => $db->getDbname(),
            'dbUser' => $db->getUser(),
            'dbPassword' => $db->getPassword(),
            'dbTablePrefix' => $db->getTablePrefix(),
            'username' => 'admin',
            'password' => 'password2',
            'agree' => true,
            'seed' => true,
        ]);
        $this->assertRetSuc($ret);
    }

    protected function buildHostAndPort(Db $db): string
    {
        return $db->getHost() . ($db->getPort() ? (':' . $db->getPort()) : '');
    }

    public function testPostCheckInstallErr()
    {
        $err = err('test-err', -1);
        $install = $this->getServiceMock(Install::class, ['checkInstall']);
        $install->expects($this->once())
            ->method('checkInstall')
            ->willReturn($err);

        $ret = Tester::postAdminApi('install');
        $this->assertSameRet($err, $ret);
    }

    public function testPostInvalidDbName()
    {
        $this->getInstallMock();

        $ret = Tester::postAdminApi('install', [
            'dbHost' => 'mysql',
            'dbDbName' => '` SELECT',
        ]);
        $this->assertRetErr($ret, '数据库名称必须匹配模式"/^[0-9a-z_]+$/i"');
    }

    public function testPostConnectDbFail()
    {
        $this->getInstallMock();

        $db = $this->wei->db;
        $host = $db->getHost();
        $ret = Tester::postAdminApi('install', [
            'dbHost' => 'invalid',
            'dbDbName' => $db->getDbname(),
            'dbUser' => $db->getUser(),
            'dbPassword' => $db->getPassword(),
            'dbTablePrefix' => $db->getTablePrefix(),
            'username' => 'admin',
            'password' => 'password2',
            'agree' => true,
            'seed' => true,
        ]);

        $this->assertTrue($ret->isErr());
        $this->assertStringStartsWith('连接数据库失败：', $ret->getMessage());

        $db->setOption('host', $host);
    }

    public function testPostCreateDatabaseFail()
    {
        $this->getInstallMock();

        $schema = $this->getServiceMock(Schema::class, [
            'hasDatabase',
            'createDatabase',
        ]);
        $schema->expects($this->once())
            ->method('hasDatabase')
            ->willReturn(false);
        $schema->expects($this->once())
            ->method('createDatabase')
            ->willThrowException(new \Exception('createDatabase fail', -1));

        $db = wei()->db;
        $ret = Tester::postAdminApi('install', [
            'dbHost' => $this->buildHostAndPort($db),
            'dbDbName' => $db->getDbname(),
            'dbUser' => $db->getUser(),
            'dbPassword' => $db->getPassword(),
            'dbTablePrefix' => $db->getTablePrefix(),
            'username' => 'admin',
            'password' => 'password2',
            'agree' => true,
            'seed' => true,
        ]);

        $this->assertRetErr($ret, '创建数据库失败，请手动创建：createDatabase fail');
    }

    public function testPostMigrationTableExists()
    {
        $this->getInstallMock();

        $schema = $this->getServiceMock(Schema::class, ['hasTable']);
        $schema->expects($this->once())
            ->method('hasTable')
            ->with('migrations')
            ->willReturn(true);

        $db = wei()->db;
        $ret = Tester::postAdminApi('install', [
            'dbHost' => $this->buildHostAndPort($db),
            'dbDbName' => $db->getDbname(),
            'dbUser' => $db->getUser(),
            'dbPassword' => $db->getPassword(),
            'dbTablePrefix' => $db->getTablePrefix(),
            'username' => 'admin',
            'password' => 'password2',
            'agree' => true,
            'seed' => true,
        ]);

        $this->assertRetErr($ret, '数据表 mx_migrations 已存在，不能安装');
    }

    public function testPostDbHostContainsPort()
    {
        $this->getInstallMock();

        $db = wei()->db;
        $ret = Tester::postAdminApi('install', [
            'dbHost' => 'invalid:port',
            'dbDbName' => $db->getDbname(),
            'dbUser' => $db->getUser(),
            'dbPassword' => $db->getPassword(),
            'dbTablePrefix' => $db->getTablePrefix(),
            'username' => 'admin',
            'password' => 'password2',
            'agree' => true,
            'seed' => true,
        ]);

        $this->assertTrue($ret->isErr());
        $this->assertStringStartsWith('连接数据库失败：', $ret->getMessage());

        $this->assertSame('port', $db->getPort());
    }

    /**
     * @return InvocationMocker
     */
    protected function getInstallMock(): InvocationMocker
    {
        return $this->getServiceMock(Install::class, ['checkInstall'])
            ->expects($this->once())
            ->method('checkInstall')
            ->willReturn(suc());
    }
}
