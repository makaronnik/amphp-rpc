<?php

namespace Makaronnik\Rpc\ProxyObjects\Utils;

use Amp\Promise;

use function Amp\call;
use function Amp\File\filesystem;

final class RpcProxyClassFileLocator
{
    public const DEFAULT_PROXY_OBJECTS_DIR_NANE = 'proxy-objects';

    protected bool $isProxyObjectsDirInitialized = false;

    /**
     * @param string|null $proxyObjectsDirPath
     */
    public function __construct(protected ?string $proxyObjectsDirPath = null)
    {
    }

    /**
     * @param bool $initDir
     * @return Promise<string>
     */
    public function getProxyObjectsDirPath(bool $initDir = true): Promise
    {
        return call(function () use ($initDir) {
            if (false === isset($this->proxyObjectsDirPath)) {
                $this->proxyObjectsDirPath = sys_get_temp_dir()
                    . DIRECTORY_SEPARATOR . self::DEFAULT_PROXY_OBJECTS_DIR_NANE;
            }

            if ($initDir) {
                yield $this->initProxyObjectsDir($this->proxyObjectsDirPath);
            }

            return $this->proxyObjectsDirPath;
        });
    }

    /**
     * @param string $proxyObjectsDirPath
     * @return Promise<void>
     */
    public function initProxyObjectsDir(string $proxyObjectsDirPath): Promise
    {
        return call(function () use ($proxyObjectsDirPath) {
            if (false === $this->isProxyObjectsDirInitialized) {
                $filesystem = filesystem();

                if (false === yield $filesystem->isDirectory($proxyObjectsDirPath)) {
                    yield $filesystem->createDirectoryRecursively($proxyObjectsDirPath);
                    $this->isProxyObjectsDirInitialized = true;
                }
            }
        });
    }
}
