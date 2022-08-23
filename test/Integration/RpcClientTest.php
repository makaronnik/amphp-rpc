<?php

namespace Makaronnik\Rpc\Test\Integration;

use Amp\Delayed;
use Amp\Http\Server\Request;
use Makaronnik\Rpc\RpcServer;
use Amp\Socket\SocketException;
use Makaronnik\Rpc\RpcRegistry;
use Makaronnik\Rpc\RpcClientBuilder;
use Makaronnik\Rpc\RpcServerFactory;
use Makaronnik\Rpc\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Makaronnik\Rpc\Enums\RpcMessageHeader;
use Makaronnik\Rpc\Exceptions\RpcException;
use Makaronnik\Rpc\Test\Support\SimpleCalc;
use Makaronnik\Rpc\Test\Support\ObjectSender;
use Makaronnik\Rpc\Test\Support\ClientRepository;
use Makaronnik\Rpc\Test\Support\IntegratedTestCase;
use Makaronnik\Rpc\Test\Support\SimpleCalcInterface;
use Makaronnik\Rpc\Responses\RetryRpcResponseFactory;
use Makaronnik\Rpc\Test\Support\UnserializableObject;
use Makaronnik\Rpc\Test\Support\ObjectSenderInterface;
use Makaronnik\Rpc\Exceptions\UnprocessedCallException;
use Makaronnik\Rpc\Responses\RedirectRpcResponseFactory;
use Makaronnik\Rpc\Test\Support\ClientRepositoryInterface;
use Makaronnik\Rpc\Exceptions\RetriesCountExceededException;
use Makaronnik\Rpc\Exceptions\PossiblyProcessedCallException;
use Makaronnik\Rpc\Exceptions\RedirectCountExceededException;

/** @psalm-suppress MissingConstructor */
class RpcClientTest extends IntegratedTestCase
{
    protected RpcRegistry $registry;
    protected RpcRequestHandler $requestHandler;
    protected RpcServer $rpcServer;

    /**
     * @return void
     * @throws SocketException
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->setTimeout(5000);

        $this->registry = new RpcRegistry();
        $this->requestHandler = new RpcRequestHandler(
            serializer: new NativeSerializer(),
            rpcRegistry: $this->registry
        );

        $this->rpcServer = (new RpcServerFactory(
            rpcServerPort: 8181,
            registry: $this->registry,
            requestHandler: $this->requestHandler
        ))->getRpcServer();
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithSuccessRpcResponse(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $this->rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            $addResult = yield $proxyCalc->add(5, 7);
        } finally {
            yield $this->rpcServer->stop();
        }

        $this->assertSame(12, $addResult);
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     * @throws SocketException
     */
    public function testRpcCallWithRedirectRpcResponse(): \Generator
    {
        $redirectWasMade = false;

        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function () {
            return (new RedirectRpcResponseFactory(
                redirectToHostOrIp: 'localhost',
                redirectToPath: '/test',
                redirectToPort: 8182,
                directDirection: true
            ))->getResponse();
        });

        $secondRpcServer = (new RpcServerFactory(
            rpcServerPort: 8182,
            registry: $this->registry,
            requestHandler: new RpcRequestHandler(
                serializer: new NativeSerializer(),
                rpcRegistry: $this->registry
            )
        ))->getRpcServer();

        $secondRpcServer->registerRequestInterceptor(function (Request $request) use (&$redirectWasMade) {
            if ($request->getUri()->getPath() !== '/test') {
                throw new RpcException("The path must be '/test'");
            }

            $redirectWasMade = true;

            return $request;
        });

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();
            yield $secondRpcServer->start();

            $addResult = yield $proxyCalc->add(5, 7);

            $this->assertSame(12, $addResult);
            $this->assertTrue($redirectWasMade);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
            yield $secondRpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     * @throws SocketException
     */
    public function testThatRpcCallWithRedirectRpcResponseThrowsExceptionIfRedirectionIsNotAllowed(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function () {
            return (new RedirectRpcResponseFactory(
                redirectToHostOrIp: 'localhost',
                redirectToPort: 8182
            ))->getResponse();
        });

        $secondRpcServer = (new RpcServerFactory(
            rpcServerPort: 8182,
            registry: $this->registry,
            requestHandler: $this->requestHandler
        ))->getRpcServer();

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withRedirectsLimit(0)
            ->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();
            yield $secondRpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(UnprocessedCallException::class, $throwable);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
            yield $secondRpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     * @throws SocketException
     */
    public function testThatRpcCallWithRedirectRpcResponseThrowsExceptionIfRedirectLimitIsExceeded(): \Generator
    {
        $redirectsCount = 0;

        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function () use (&$redirectsCount) {
            /** @psalm-suppress MixedAssignment, MixedOperand */
            $redirectsCount++;

            return (new RedirectRpcResponseFactory(
                redirectToHostOrIp: 'localhost',
                redirectToPort: 8182
            ))->getResponse();
        });

        $secondRpcServer = (new RpcServerFactory(
            rpcServerPort: 8182,
            registry: $this->registry,
            requestHandler: $this->requestHandler
        ))->getRpcServer();

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withRedirectsLimit(3)
            ->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();
            yield $secondRpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(RedirectCountExceededException::class, $throwable);
            $this->assertGreaterThan(3, $redirectsCount);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
            yield $secondRpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithRetryRpcResponse(): \Generator
    {
        $retryWasMade = false;

        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        /** @psalm-suppress UnusedClosureParam */
        $interceptorId = $this->rpcServer->registerRequestInterceptor(
            function (Request $request, string $interceptorId) use (&$retryWasMade) {
                $this->requestHandler->unregisterInterceptor($interceptorId);

                $retryWasMade = true;

                return (new RetryRpcResponseFactory())->getResponse();
            }
        );

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $this->rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            $addResult = yield $proxyCalc->add(5, 7);

            $this->assertSame(12, $addResult);
            $this->assertTrue($retryWasMade);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithRetryRpcResponseThrowsExceptionIfRetriesIsNotAllowed(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function () {
            return (new RetryRpcResponseFactory())->getResponse();
        });

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withRetryingLimit(0)
            ->withRetryingDelayInMs(0)
            ->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(UnprocessedCallException::class, $throwable);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithRetryRpcResponseThrowsExceptionIfRetryingLimitIsExceeded(): \Generator
    {
        $retriesCount = 0;

        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function () use (&$retriesCount) {
            /** @psalm-suppress MixedAssignment, MixedOperand */
            $retriesCount++;

            return (new RetryRpcResponseFactory())->getResponse();
        });

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withRetryingLimit(3)
            ->withRetryingDelayInMs(0)
            ->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(RetriesCountExceededException::class, $throwable);
            $this->assertGreaterThan(3, $retriesCount);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithThrowableResult(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $this->rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyCalc->div(4, 0);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(PossiblyProcessedCallException::class, $throwable);
            $this->assertSame('DivisionByZeroError', $throwable->getPreviousThrowableClassName());
        } finally {
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithTargetEntity(): \Generator
    {
        $targetEntityId = null;

        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: ClientRepositoryInterface::class,
            remoteObjectClassName: ClientRepository::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function (Request $request) use (&$targetEntityId) {
            $targetEntityId = $request->getHeader(RpcMessageHeader::TargetEntity->value);

            return $request;
        });

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withTargetEntityIdParamPosition(0)
            ->build();

        $proxyClientRepository = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: ClientRepositoryInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyClientRepository->getClientName(777);

            $this->assertSame(777, (int) $targetEntityId);
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testRpcCallWithExceededTimeoutThrowsException(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: SimpleCalc::class
        );

        $interceptorId = $this->rpcServer->registerRequestInterceptor(function (Request $request) {
            yield new Delayed(150);

            return $request;
        });

        $rpcClient = (new RpcClientBuilder(
            rpcServerHostnameOrIp: 'localhost',
            rpcServerPort: 8181
        ))
            ->withRequestTimeoutInMs(100)
            ->build();

        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(PossiblyProcessedCallException::class, $throwable);
            $this->assertSame(\Amp\Http\Client\SocketException::class, $throwable->getPreviousThrowableClassName());
        } finally {
            $this->rpcServer->unregisterRequestInterceptor($interceptorId);
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     */
    public function testThatDoRpcCallWithoutRegisteredRemoteObjectThrowsException(): \Generator
    {
        $proxyCalc = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $this->rpcClient,
            interfaceClassName: SimpleCalcInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyCalc->add(5, 7);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(RpcException::class, $throwable);
        } finally {
            yield $this->rpcServer->stop();
        }
    }

    /**
     * @return \Generator
     * @throws \ReflectionException
     */
    public function testThatUnserializableRpcParamThrowsException(): \Generator
    {
        $this->registry->clearRegistry();
        $this->registry->registerRemoteObject(
            remoteInterfaceName: ObjectSenderInterface::class,
            remoteObjectClassName: ObjectSender::class
        );

        $proxyObjectSender = yield $this->proxyObjectsFactory->createProxy(
            rpcClient: $this->rpcClient,
            interfaceClassName: ObjectSenderInterface::class
        );

        try {
            yield $this->rpcServer->start();

            yield $proxyObjectSender->sendObject(new UnserializableObject());
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(RpcException::class, $throwable);
        } finally {
            yield $this->rpcServer->stop();
        }
    }
}
