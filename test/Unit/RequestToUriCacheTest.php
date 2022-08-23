<?php

namespace Makaronnik\Rpc\Test\Unit;

use cash\LRUCache;
use DG\BypassFinals;
use Makaronnik\Rpc\RpcClient;
use Amp\Http\Client\HttpClient;
use PHPUnit\Framework\TestCase;
use Amp\Serialization\Serializer;
use Makaronnik\Rpc\Dns\DnsResolver;
use Makaronnik\Rpc\RpcClientConfig;
use Makaronnik\Rpc\RpcResponseHandlerInterface;
use Makaronnik\Rpc\Test\Support\ClientRepositoryInterface;

/** @psalm-suppress MissingConstructor */
class RequestToUriCacheTest extends TestCase
{
    protected RpcClient $rpcClient;

    /**
     * @return void
     */
    public function setUp(): void
    {
        BypassFinals::enable();

        $this->rpcClient = new RpcClient(
            uri: 'localhost',
            config: \Mockery::mock(RpcClientConfig::class),
            responseHandler: \Mockery::mock(RpcResponseHandlerInterface::class),
            serializer: \Mockery::mock(Serializer::class),
            httpClient:  \Mockery::mock(HttpClient::class),
            dnsResolver: \Mockery::mock(DnsResolver::class),
            requestToUriCache: new LRUCache(100)
        );
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        \Mockery::close();
    }

    /**
     * @return string
     */
    public function testRequestToUriCacheKeyBuilding(): string
    {
        $requestToUriCacheKey = $this->rpcClient->buildRequestToUriCacheKey(
            hostName: 'localhost',
            interfaceClassName: ClientRepositoryInterface::class,
            methodName: 'getClientName',
            targetEntityId: '777'
        );

        $this->assertSame(
            'localhost->Makaronnik\Rpc\Test\Support\ClientRepositoryInterface::getClientName_777',
            $requestToUriCacheKey
        );

        return $requestToUriCacheKey;
    }

    /**
     * @depends testRequestToUriCacheKeyBuilding
     * @param string $requestToUriCacheKey
     * @return void
     */
    public function testPutItemToRequestToUriCache(string $requestToUriCacheKey): void
    {
        $cachedIp = '127.0.0.1';

        $this->rpcClient->putItemToRequestToUriCache($requestToUriCacheKey, $cachedIp);

        $this->assertSame($cachedIp, $this->rpcClient->getItemFromRequestToUriCache($requestToUriCacheKey));
    }

    /**
     * @depends testRequestToUriCacheKeyBuilding
     * @param string $requestToUriCacheKey
     * @return void
     */
    public function testRemoveItemFromRequestToUriCache(string $requestToUriCacheKey): void
    {
        $cachedIp = '127.0.0.1';

        $this->rpcClient->putItemToRequestToUriCache($requestToUriCacheKey, $cachedIp);
        $this->assertSame($cachedIp, $this->rpcClient->getItemFromRequestToUriCache($requestToUriCacheKey));
        $this->rpcClient->removeItemFromRequestToUriCache($requestToUriCacheKey);
        $this->assertNull($this->rpcClient->getItemFromRequestToUriCache($requestToUriCacheKey));
    }
}
