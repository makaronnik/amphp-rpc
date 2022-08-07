<?php

namespace Makaronnik\Rpc;

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Amp\Http\Server\HttpServer;
use Amp\Socket\Server as SocketServer;

final class RpcServerFactory
{
    /**
     * @param int $rpcServerPort
     * @param RpcRegistry $registry
     * @param RpcRequestHandler $requestHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected int $rpcServerPort,
        protected readonly RpcRegistry $registry,
        protected readonly RpcRequestHandler $requestHandler,
        protected readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * @return RpcServer
     * @throws \Amp\Socket\SocketException
     */
    public function getRpcServer(): RpcServer
    {
        $servers = [
            SocketServer::listen("0.0.0.0:" . $this->rpcServerPort),
            SocketServer::listen("[::]:" . $this->rpcServerPort),
        ];

        $httpServer = new HttpServer(
            $servers,
            $this->requestHandler,
            $this->logger
        );

        return new RpcServer(
            httpServer: $httpServer,
            socketServers: $servers,
            registry: $this->registry,
            requestHandler: $this->requestHandler,
            logger: $this->logger
        );
    }
}
