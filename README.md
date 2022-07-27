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

## Versioning
`makaronnik/amphp-rpc` follows the [semver](http://semver.org/) semantic versioning specification.

## License
The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
