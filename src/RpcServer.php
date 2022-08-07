<?php

namespace Makaronnik\Rpc;

use Amp\Promise;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Amp\Http\Server\HttpServer;
use Amp\Socket\Server as SocketServer;
use function Amp\call;

final class RpcServer
{
    /**
     * @param HttpServer $httpServer
     * @param SocketServer[] $socketServers
     * @param RpcRegistry $registry
     * @param RpcRequestHandler $requestHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly HttpServer $httpServer,
        protected readonly array $socketServers,
        protected readonly RpcRegistry $registry,
        protected readonly RpcRequestHandler $requestHandler,
        protected readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * @param callable $interceptor
     * @return string
     */
    public function registerRequestInterceptor(callable $interceptor): string
    {
        return $this->requestHandler->registerInterceptor($interceptor);
    }

    /**
     * @param string $interceptorUniqId
     * @return void
     */
    public function unregisterRequestInterceptor(string $interceptorUniqId): void
    {
        $this->requestHandler->unregisterInterceptor($interceptorUniqId);
    }

    /**
     * @return Promise
     */
    public function start(): Promise
    {
        return call(function () {
            yield $this->httpServer->start();

            $this->logger->info('Rpc server started successfully');
        });
    }

    /**
     * @return Promise<void>
     */
    public function stop(): Promise
    {
        return call(function () {
            yield $this->stopHttpServer();

            $this->stopSocketServers();

            $this->logger->info('Rpc server stopped successfully');
        });
    }

    /**
     * @return Promise
     */
    protected function stopHttpServer(): Promise
    {
        return call(function () {
            yield $this->httpServer->stop();
        });
    }

    /**
     * @return void
     */
    protected function stopSocketServers(): void
    {
        if (false === empty($this->socketServers)) {
            foreach ($this->socketServers as $socketServer) {
                if (false === $socketServer->isClosed()) {
                    $socketServer->close();
                }
            }
        }
    }
}
