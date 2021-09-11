<?php

namespace Miaoxing\Install\Service;

use Miaoxing\Plugin\BaseService;
use Miaoxing\Plugin\Service\AppModel;
use Miaoxing\Plugin\Service\Ret;
use Wei\Time;

/**
 * Install
 *
 * @mixin \ReqMixin
 * @mixin \ResMixin
 * @mixin \AppMixin
 * @mixin \EnvMixin
 * @mixin \UrlMixin
 * @mixin \LoggerMixin
 * @mixin \DbMixin
 */
class Install extends BaseService
{
    /**
     * @var string
     */
    protected $lockFile = 'storage/install.lock.php';

    /**
     * 安装页面所在的路径
     *
     * @var string
     */
    protected $installUrl = 'admin/install';

    /**
     * 要检查的目录
     *
     * @var string[]
     */
    protected $checkDirs = [
        'storage',
        'public/uploads',
    ];

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

        $this->initApp();
    }

    /**
     * 安装时，还没有数据库连接，AppModel 不能读取到数据，因此需自行初始化
     *
     * @internal
     */
    protected function initApp()
    {
        $this->app->setId(1);
        $this->app->setModel(AppModel::new([
            'id' => 1,
            'name' => 'app',
            'pluginIds' => [
                'app',
                // 安装页面使用后台的页面样式
                'admin',
            ],
        ], [
            // IMPORTANT 当前是初始化流程，需要明确传入 wei 对象，才不会自动生成一个新的 wei 对象
            'wei' => $this->wei,
            // 初始化时，CastTrait::isIgnoreCast 要用到 db 服务
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
     * Return lock file path
     *
     * @return string
     * @svc
     */
    protected function getLockFile(): string
    {
        return $this->lockFile;
    }

    /**
     * 将安装信息写入锁定文件中
     *
     * @svc
     */
    protected function writeLockFile()
    {
        $this->logger->info('write install lock');
        $content = [
            'installedAt' => Time::now(),
        ];
        $content = "<?php\n\nreturn " . var_export($content, true) . ";\n";
        file_put_contents($this->lockFile, $content);
    }

    /**
     * @svc
     */
    protected function isInstalled()
    {
        return file_exists($this->lockFile);
    }

    /**
     * 检查能否进行安装
     *
     * @return Ret
     * @svc
     */
    protected function checkInstall()
    {
        if ($this->isInstalled()) {
            // 如果已安装过，直接返回，不再检查其他项目，避免泄露配置
            return $this->buildInstallRet([
                err(['程序已安装过，如需重新安装，请手动删除：%s', $this->lockFile]),
            ]);
        }

        $reqs = [];
        $reqs[] = $this->getPhpVersionReq();
        $reqs = array_merge($reqs, $this->checkDirs(), $this->checkExts());

        return $this->buildInstallRet($reqs);
    }

    /**
     * 将多个返回值合并为一个，如果有错误，则使用第一个错误信息作为主错误信息
     *
     * @param array $rets
     * @return Ret
     */
    protected function buildInstallRet(array $rets): Ret
    {
        $errMessage = null;
        foreach ($rets as $ret) {
            if ($ret->isErr()) {
                $errMessage = $ret->getMessage();
                break;
            }
        }

        return ret([
            'message' => $errMessage,
            'code' => $errMessage ? -1 : 0,
            'data' => $rets,
        ]);
    }

    /**
     * @return array<Ret>
     */
    protected function checkDirs(): array
    {
        clearstatcache();

        $rets = [];
        foreach ($this->checkDirs as $dir) {
            if (!is_writable($dir)) {
                $rets[] = err(['目录 %s 不可写', $dir]);
            } else {
                $rets[] = suc(['目录 %s 可写', $dir]);
            }
        }
        return $rets;
    }

    /**
     * 检查扩展是否已安装
     *
     * @return array<Ret>
     */
    protected function checkExts(): array
    {
        $extensions = [];
        $files = glob('plugins/*/composer.json');
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            foreach ($content['require'] ?? [] as $name => $version) {
                if ('ext-' === substr($name, 0, 4)) {
                    $extensions[substr($name, 4)] = true;
                }
            }
        }

        $rets = [];
        foreach ($extensions as $name => $flag) {
            $rets[] = extension_loaded($name) ? suc(['扩展 %s 已安装', $name]) : err(['扩展 %s 未安装', $name]);
        }
        return $rets;
    }

    /**
     * 获取 PHP 版本依赖提示
     *
     * 注意在入口 composer 已经做了版本检查，这里不做检查，也不适合做检查，只用于提示
     *
     * @return Ret
     * @internal
     */
    protected function getPhpVersionReq(): Ret
    {
        $content = json_decode(file_get_contents('composer.json'), true);
        $version = $content['require']['php'] ?? null;
        return suc(['PHP 版本为 %s，%s', \PHP_VERSION, $version]);
    }

    /**
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    protected function exit()
    {
        exit;
    }
}
