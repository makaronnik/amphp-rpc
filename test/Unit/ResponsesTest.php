<?php

namespace Makaronnik\Rpc\Test\Unit;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Serialization\NativeSerializer;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Enums\RpcThrowableType;
use Makaronnik\Rpc\Exceptions\RpcException;
use Makaronnik\Rpc\Responses\RetryRpcResponseFactory;
use Makaronnik\Rpc\Responses\SuccessRpcResponseFactory;
use Makaronnik\Rpc\Responses\RedirectRpcResponseFactory;
use Makaronnik\Rpc\Responses\ThrowableRpcResponseFactory;
use Makaronnik\Rpc\Responses\HealthCheckRpcResponseFactory;

class ResponsesTest extends AsyncTestCase
{
    /**
     * @return \Generator
     */
    public function testSuccessRpcResponseFactory(): \Generator
    {
        $content = 'Successful operation result';
        $serializedContent = serialize($content);
        $response = (new SuccessRpcResponseFactory($serializedContent))->getResponse();
        $contentFromResponse = yield $response->getBody()->read();

        $this->assertNotNull($contentFromResponse);

        $unserializedContent = unserialize($contentFromResponse);

        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame($response->getHeader(RpcMessageHeader::ResponseType->value), RpcResponseType::Success->value);
        $this->assertSame($response->getHeader(RpcMessageHeader::WithSerializedContent->value), 'true');
        $this->assertCount(3, $response->getHeaders());
        $this->assertSame($content, $unserializedContent);
    }

    /**
     * @return \Generator
     */
    public function testHealthCheckRpcResponseFactory(): \Generator
    {
        $response = (new HealthCheckRpcResponseFactory())->getResponse();
        $contentFromResponse = yield $response->getBody()->read();

        $this->assertEmpty($contentFromResponse);
        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame($response->getHeader(RpcMessageHeader::ResponseType->value), RpcResponseType::Success->value);
        $this->assertCount(2, $response->getHeaders());
    }

    /**
     * @dataProvider directDirectionDataProvider
     * @param bool $directDirectionParam
     * @param mixed $directDirectionHeader
     * @param int $headersCount
     * @return \Generator
     */
    public function testRedirectRpcResponseFactory(
        bool $directDirectionParam,
        mixed $directDirectionHeader,
        int $headersCount
    ): \Generator {
        $host = 'google.com';
        $path = '/test';
        $port = '777';
        $response = (new RedirectRpcResponseFactory(
            redirectToHostOrIp: $host,
            redirectToPath: $path,
            redirectToPort: (int) $port,
            directDirection: $directDirectionParam
        ))->getResponse();
        $contentFromResponse = yield $response->getBody()->read();

        $this->assertEmpty($contentFromResponse);
        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame($response->getHeader(RpcMessageHeader::ResponseType->value), RpcResponseType::Redirect->value);
        $this->assertSame($response->getHeader(RpcMessageHeader::RedirectToHostOrIp->value), $host);
        $this->assertSame($response->getHeader(RpcMessageHeader::RedirectToPath->value), $path);
        $this->assertSame($response->getHeader(RpcMessageHeader::RedirectToPort->value), $port);
        $this->assertSame($response->getHeader(RpcMessageHeader::DirectDirection->value), $directDirectionHeader);
        $this->assertCount($headersCount, $response->getHeaders());
    }

    /**
     * @return array[]
     */
    protected function directDirectionDataProvider(): array
    {
        return [
            [true, 'true', 6],
            [false, null, 5]
        ];
    }

    /**
     * @dataProvider retryDelayDataProvider
     * @param int|null $retryDelayParam
     * @param string|null $retryDelayHeader
     * @param int $headersCount
     * @return \Generator
     */
    public function testRetryRpcResponseFactory(
        int|null $retryDelayParam,
        string|null $retryDelayHeader,
        int $headersCount
    ): \Generator {
        $response = (new RetryRpcResponseFactory($retryDelayParam))->getResponse();
        $contentFromResponse = yield $response->getBody()->read();

        $this->assertEmpty($contentFromResponse);
        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame($response->getHeader(RpcMessageHeader::ResponseType->value), RpcResponseType::Retry->value);
        $this->assertSame($response->getHeader(RpcMessageHeader::RetryWithDelay->value), $retryDelayHeader);
        $this->assertCount($headersCount, $response->getHeaders());
    }

    /**
     * @return array[]
     */
    protected function retryDelayDataProvider(): array
    {
        return [
            [100500, '100500', 3],
            [null, null, 2]
        ];
    }

    /**
     * @return \Generator
     */
    public function testThrowableRpcResponseFactory(): \Generator
    {
        $serializer = new NativeSerializer();
        $errorMessage = 'Some error';
        $errorCode = '555';
        $throwable = new RpcException($errorMessage, (int) $errorCode);
        $throwableType = RpcThrowableType::Unprocessed;
        $response = (new ThrowableRpcResponseFactory(
            serializer: $serializer,
            throwable: $throwable,
            throwableType: $throwableType
        ))->getResponse();
        $contentFromResponse = yield $response->getBody()->read();

        $this->assertSame($contentFromResponse, $errorMessage);
        $this->assertTrue($response->hasHeader('content-length'));
        $this->assertSame($response->getHeader(RpcMessageHeader::ResponseType->value), RpcResponseType::Throwable->value);
        $this->assertSame($response->getHeader(RpcMessageHeader::ThrowableType->value), $throwableType->value);
        $this->assertSame($response->getHeader(RpcMessageHeader::ThrowableCode->value), $errorCode);
        $this->assertSame($response->getHeader(RpcMessageHeader::ThrowableClass->value), \get_class($throwable));
        $this->assertCount(5, $response->getHeaders());
    }
}
