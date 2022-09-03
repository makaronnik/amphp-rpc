<?php
require './../../vendor/autoload.php';

use Amp\Loop;
use Amp\Promise;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Socket\SocketException;
use Makaronnik\Rpc\RpcRegistry;
use Makaronnik\Rpc\RpcClientBuilder;
use Makaronnik\Rpc\RpcServerFactory;
use Makaronnik\Rpc\RpcRequestHandler;
use Amp\Serialization\NativeSerializer;
use Makaronnik\Rpc\Responses\RetryRpcResponseFactory;
use Makaronnik\Rpc\ProxyObjects\RpcProxyObjectFactory;
use Makaronnik\Rpc\ProxyObjects\RpcProxyObjectGenerator;
use Makaronnik\Rpc\Responses\RedirectRpcResponseFactory;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassFileLocator;
use function Amp\call;

/**
 * Remote Object Interface.
 * MUST be on both server and client.
 */
interface SimpleCalcInterface
{
    public function add(int $a, int $b): Promise;
    public function sub(int $a, int $b): Promise;
    public function mul(int $a, int $b): Promise;
    public function div(int $a, int $b): Promise;
}

/**
 * Real Remote Object Class.
 * MUST be on the server.
 */
class SimpleCalc implements SimpleCalcInterface
{
    public function add(int $a, int $b): Promise
    {
        return call(fn (): int => $a + $b);
    }

    public function sub(int $a, int $b): Promise
    {
        return call(fn (): int => $a - $b);
    }

    public function mul(int $a, int $b): Promise
    {
        return call(fn (): int => $a * $b);
    }

    public function div(int $a, int $b): Promise
    {
        return call(fn (): float => $a / $b);
    }
}

/**
 * Setting up the RPC Servers.
 */
$registry = new RpcRegistry();

// Registering a Remote Object.
try {
    $registry->registerRemoteObject(SimpleCalcInterface::class, SimpleCalc::class);
} catch (ReflectionException $exception) {
    printf('Exception: %s. Error message: %s', \get_class($exception), $exception->getMessage());
    Loop::stop();
    exit();
}

$requestHandler = new RpcRequestHandler(
    serializer: new NativeSerializer(),
    rpcRegistry: $registry
);

$doRetry = true;
$doRedirect = true;

// Do single retry, then skip. RetryRpcResponse example.
$requestHandler->registerInterceptor(static function (Request $request) use (&$doRetry): Request|Response {
    if ($doRetry) {
        $doRetry = false;
        print "Retry \r\n";

        return (new RetryRpcResponseFactory(1000))->getResponse();
    }

    return $request;
});

// Do single redirect to second server, then skip. RedirectRpcResponse example.
$requestHandler->registerInterceptor(static function (Request $request) use (&$doRedirect): Request|Response {
    if ($doRedirect) {
        $doRedirect = false;
        print "Redirect \r\n";

        return (new RedirectRpcResponseFactory(
            redirectToHostOrIp: 'localhost',
            redirectToPort: 8182,
            directDirection: true
        ))->getResponse();
    }

    return $request;
});

// Setting up First RPC Server with port 8181.
try {
    $firstRpcServer = (new RpcServerFactory(
        rpcServerPort: 8181,
        registry: $registry,
        requestHandler: $requestHandler
    ))->getRpcServer();
} catch (SocketException $exception) {
    printf('Exception: %s. Error message: %s', \get_class($exception), $exception->getMessage());
    Loop::stop();
    exit();
}

// Setting up Second RPC Server with port 8182.
try {
    $secondRpcServer = (new RpcServerFactory(
        rpcServerPort: 8182,
        registry: $registry,
        requestHandler: $requestHandler
    ))->getRpcServer();
} catch (SocketException $exception) {
    printf('Exception: %s. Error message: %s', \get_class($exception), $exception->getMessage());
    Loop::stop();
    exit();
}

/**
 * Setting up the RPC Client. Default server port 8181/.
 */
$rpcClient = (new RpcClientBuilder(
    rpcServerHostnameOrIp: 'localhost',
    rpcServerPort: 8181
))->build();

// Best to set up with DI container
$proxyFilesLocator = new RpcProxyClassFileLocator();
$proxyObjectsGenerator = new RpcProxyObjectGenerator($proxyFilesLocator);
$proxyObjectsFactory = new RpcProxyObjectFactory($proxyFilesLocator, $proxyObjectsGenerator);

/**
 * Running the example.
 */
Loop::run(static function () use ($proxyObjectsFactory, $rpcClient, $firstRpcServer, $secondRpcServer) {
    yield $firstRpcServer->start();
    yield $secondRpcServer->start();

    // Create Remote Proxy Object.
    /** @var SimpleCalc $proxyCalc */
    $proxyCalc = yield $proxyObjectsFactory->createProxy($rpcClient, SimpleCalcInterface::class);

    // Remote Add operation. SuccessRpcResponse example.
    /** @var int $addResult */
    $addResult = yield $proxyCalc->add(5, 7);

    printf('%d + %d = %d' . "\r\n", 5, 7, $addResult);

    // Remote Division operation. ThrowableRpcResponse example.
    try {
        /** @var float $divResult */
        $divResult = yield $proxyCalc->div(4, 0);
    } catch (Throwable $throwable) {
        $divResult = sprintf('Operation failed. %s', $throwable->getMessage());
    }

    printf('4 / 0 = %s', $divResult);

    // Stopping servers, stopping the loop and exiting the script.
    yield $firstRpcServer->stop();
    yield $secondRpcServer->stop();
    Loop::stop();
    exit();
});
