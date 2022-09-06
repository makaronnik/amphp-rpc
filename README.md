[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

[![Stand With Ukraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner2-direct.svg)](https://vshymanskyy.github.io/StandWithUkraine/)

# Amphp RPC
PHP (8.1) Async RPC based on [Amp](https://amphp.org/)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require makaronnik/amphp-rpc
```

## Requirements
- PHP 8.1+

## Description
This is an RPC (remote procedure calls) package that works in asynchronous, non-blocking mode, based on [Amp](https://amphp.org/).  
Based on ideas implemented in [amphp/rpc](https://github.com/amphp/rpc), but enhanced with advanced functionality useful for communication and load balancing in microservice architecture.  
Used in the [Amphp Microservice Framework](https://github.com/makaronnik/amphp-microservice-framework) as one of the main methods of inter-service communication. It also serves as the basis for the Amphp Request Proxy package.

## Main features
- Calling procedures (methods) by the client on a remote server (service), with the possibility of obtaining a result.
- Exceptions that occur on the server during the execution of procedures are caught and transferred to the client, where they can be caught and processed.
- Different response options from the server for various situations (redirect to another host/ip, try again after n seconds, etc.) and the corresponding client reaction. You can implement your own response options and their processing using the interceptor mechanism.
- Cache for `['$host . $className . $methodName' => 'URI']` pairs to store and reuse redirect targets to reduce the number of possible intermediate requests

## Creating Remote Objects
Remote objects are, in fact, instances of classes that are generated on the side of the RPC server when an RPC request is received from an RPC client. These classes MUST be on the server side and implement their interfaces. The public methods of these interfaces MUST return an Amp\Promise object or an exception will be thrown. These methods are called Remote Procedures. They will be executed on the server as a result of calling similar methods on the proxy object on the client. In order for this to happen, the map between the remote object's interface and its implementation must be registered. This is done using the `registerRemoteObject` method of the RpcRegistry object, which is passed to the RpcServer constructor. The identical interface of the remote object MUST be on BOTH the server and the client. An identical interface on the client is needed to create the appropriate proxy for a remote object, on the client side, by calling the `createProxy` method on the RpcProxyObjectFactory.

#### Remote Object Interface example:
```php
use Amp\Promise;

interface SimpleCalcInterface
{
    public function add(int $a, int $b): Promise; // remote procedure add() API
}
```

#### Remote Object Implementation example:
```php
use Amp\Promise;
use function Amp\call;

class SimpleCalc implements SimpleCalcInterface
{
    public function add(int $a, int $b): Promise // remote procedure add() implementation
    {
        return call(fn (): int => $a + $b); // returns Promise<int>
    }
}
```

#### (Server) Registering a mapping between a remote object's interface and its implementation:
```php
$registry = new RpcRegistry();
$registry->registerRemoteObject(SimpleCalcInterface::class, SimpleCalc::class);
$requestHandler = new RpcRequestHandler(new NativeSerializer(), $registry);
$rpcServer = (new RpcServerFactory(8181, $registry, $requestHandler))->getRpcServer();
yield $rpcServer->start();
```

#### (Client) Creating a remote object proxy and calling its remote method:
```php
$rpcClient = (new RpcClientBuilder('localhost', 8181))->build();
$proxyCalc = yield $proxyObjectsFactory->createProxy($rpcClient, SimpleCalcInterface::class);
$addResult = yield $proxyCalc->add(5, 7); // int: 12
```

## Complete examples
You can find complete examples in the [examples](/examples/simple-calc) and [test](/test) directories.

## Main package classes

### Server side:
- #### [RpcServer](/src/RpcServer.php) - starts a server serving RPC requests. It is preferable to configure it via [RpcServerFactory](/src/RpcServerFactory.php).
- #### [RpcRequestHandler](/src/RpcRequestHandler.php) - handles RPC requests. Has a `registerInterceptor` method for registering request interceptors that are executed before the main request processing logic. After the request has been processed, it returns the appropriate RPC response.
- #### [RpcRegistry](/src/RpcRegistry.php) - stores links between interfaces of remote objects and their implementations. If you do not register a remote interface mapping with its implementation, then the server will not know how to handle the RPС request. Passed to the server's and handler's constructors.

### Client side:
- #### [RpcClient](/src/RpcClient.php) - makes RPC requests to the server. It is preferable to configure it via [RpcClientBuilder](/src/RpcClientBuilder.php). Not used directly, passed to the remote object's proxy creation method.
- #### [RpcResponseHandler](/src/RpcResponseHandler.php) - handles responses from the RPC server and returns the result of the RPC request or throws an appropriate exception.
- #### [RpcProxyObjectFactory](/src/ProxyObjects/RpcProxyObjectFactory.php) - creates a proxy for the remote object. It has a single public method `createProxy` that takes as parameters an RpcClient object and the fully qualified interface name of the target remote object. If the previously generated proxy cannot be found, then the generator is used.
- #### [RpcProxyObjectGenerator](/src/ProxyObjects/RpcProxyObjectGenerator.php) - generates a proxy class file for the remote object. It is passed to the factory constructor, where it is used.
- #### [RpcProxyClassFileLocator](/src/ProxyObjects/Utils/RpcProxyClassFileLocator.php) - configures a directory path to store generated proxy classes for remote objects. It is passed to the factory constructor, where it is used.

### Responces factories:
- #### [SuccessRpcResponseFactory](/src/Responses/SuccessRpcResponseFactory.php) - creates an RPC response containing the serialized result of the RPC request.
- #### [ThrowableRpcResponseFactory](/src/Responses/ThrowableRpcResponseFactory.php) - creates an RPC response containing data about an exception that occurred on the server during the processing of an RPC request.
- #### [RetryRpcResponseFactory](/src/Responses/RetryRpcResponseFactory.php) - creates an RPC response that tells the RPC client (handler) to retry the request after a certain period of time.
- #### [RedirectRpcResponseFactory](/src/Responses/RetryRpcResponseFactory.php) - creates an RPC response that tells the RPC client (handler) to resubmit the request, but to a different host, or with a different port or path.

### Exceptions:
- #### [UnprocessedCallException](/src/Exceptions/UnprocessedCallException.php) - thrown if the RPC request was not completed on the RPC server and it can be safely retried.
- #### [PossiblyProcessedCallException](/src/Exceptions/PossiblyProcessedCallException.php) - thrown if the RPC request was partially completed on the RPC server and its retry may be unsafe.
- #### [ProcessedCallException](/src/Exceptions/ProcessedCallException.php) - thrown if the request was successful on the server, but there was a problem getting or returning the result, and repeating this request is likely to be unsafe.
- #### [RetriesCountExceededException ](/src/Exceptions/RetriesCountExceededException.php) - thrown if the allowed number of retries has been exceeded. This exception inherits from UnprocessedCallException so the request can be retried safely.
- #### [RedirectCountExceededException](/src/Exceptions/RedirectCountExceededException.php) - thrown if the allowed number of redirects has been exceeded. This exception inherits from UnprocessedCallException so the request can be retried safely.

## Versioning
`makaronnik/amphp-rpc` follows the [semver](http://semver.org/) semantic versioning specification.

## License
The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
