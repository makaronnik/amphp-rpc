<?php

namespace Makaronnik\Rpc\Test\Support;

use Amp\Promise;
use Makaronnik\Rpc\RpcClient;
use Amp\PHPUnit\AsyncTestCase;
use Makaronnik\Rpc\RpcClientBuilder;
use Makaronnik\Rpc\ProxyObjects\RpcProxyObjectFactory;
use Makaronnik\Rpc\ProxyObjects\RpcProxyObjectGenerator;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassFileLocator;

use function Amp\call;
use function Amp\File\filesystem;

/** @psalm-suppress MissingConstructor, RedundantPropertyInitializationCheck */
class IntegratedTestCase extends AsyncTestCase
{
    protected RpcClient $rpcClient;
    protected RpcProxyClassFileLocator $proxyFilesLocator;
    protected RpcProxyObjectGenerator $proxyObjectsGenerator;
    protected RpcProxyObjectFactory $proxyObjectsFactory;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->setTimeout(5000);

        $this->rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: '127.0.0.1',
            rpcServerPort: 8181
        ))->build();

        $this->proxyFilesLocator = new RpcProxyClassFileLocator();
        $this->proxyObjectsGenerator = new RpcProxyObjectGenerator($this->proxyFilesLocator);
        $this->proxyObjectsFactory = new RpcProxyObjectFactory(
            proxyClassFileLocator: $this->proxyFilesLocator,
            proxyObjectGenerator: $this->proxyObjectsGenerator
        );
    }

    /**
     * @return Promise
     */
    protected function removeTempDir(): Promise
    {
        return call(function () {
            $filesystem = filesystem();
            $proxyObjectsDir = yield $this->proxyFilesLocator->getProxyObjectsDirPath(false);

            if (yield $filesystem->isDirectory($proxyObjectsDir)) {
                $proxyFiles = yield $filesystem->listFiles($proxyObjectsDir);

                if (false === empty($proxyFiles)) {
                    foreach ($proxyFiles as $file) {
                        yield $filesystem->deleteFile($proxyObjectsDir. '/' . $file);
                    }
                }

                yield $filesystem->deleteDirectory($proxyObjectsDir);
            }
        });
    }
}
