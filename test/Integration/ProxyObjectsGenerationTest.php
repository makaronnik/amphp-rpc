<?php

namespace Makaronnik\Rpc\Test\Integration;

use Makaronnik\Rpc\Test\Support\SimpleCalc;
use Makaronnik\Rpc\ProxyObjects\BaseProxyObject;
use Makaronnik\Rpc\Test\Support\IntegratedTestCase;
use Makaronnik\Rpc\Test\Support\SimpleCalcInterface;

use function Amp\File\filesystem;

/** @psalm-suppress MissingConstructor */
class ProxyObjectsGenerationTest extends IntegratedTestCase
{
    /**
     * @return \Generator
     */
    public function testSuccessfulProxyObjectGeneration(): \Generator
    {
        yield $this->removeTempDir();

        /** @var BaseProxyObject $proxy */
        $proxy = yield $this->proxyObjectsFactory->createProxy(
            $this->rpcClient,
            SimpleCalcInterface::class
        );

        $this->assertInstanceOf(BaseProxyObject::class, $proxy);

        $reflectedProxy = new \ReflectionClass($proxy);
        $proxyFile = $reflectedProxy->getFileName();
        $filesystem = filesystem();

        $this->assertTrue(yield $filesystem->isFile($proxyFile));

        $proxyFileContent = yield $filesystem->read($proxyFile);
        $this->assertStringContainsString(SimpleCalcInterface::class, $proxyFileContent);
    }

    /**
     * @return \Generator
     */
    public function testThatGeneratingProxyObjectForNonInterfaceThrowsException(): \Generator
    {
        $this->expectException(\RuntimeException::class);

        yield $this->proxyObjectsFactory->createProxy(
            $this->rpcClient,
            SimpleCalc::class
        );
    }
}
