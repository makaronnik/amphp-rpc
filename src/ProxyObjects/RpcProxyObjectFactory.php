<?php

namespace Makaronnik\Rpc\ProxyObjects;

use Amp\Promise;
use ReflectionClass;
use Makaronnik\Rpc\RpcClient;
use Composer\InstalledVersions;
use Makaronnik\Rpc\Exceptions\RpcException;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassFileLocator;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassNameInflector;

use function Amp\call;
use function Amp\File\read;
use function Amp\File\filesystem;

final class RpcProxyObjectFactory
{
    protected ?string $packageVersionReference = null;

    /**
     * @param RpcProxyClassFileLocator $proxyClassFileLocator
     * @param RpcProxyObjectGenerator $proxyObjectGenerator
     */
    public function __construct(
        protected RpcProxyClassFileLocator $proxyClassFileLocator,
        protected RpcProxyObjectGenerator $proxyObjectGenerator
    ) {
    }

    /**
     * @psalm-param class-string<RemoteObjectType> $interfaceClassName
     * @psalm-return Promise<RemoteObjectType>
     * @psalm-template RemoteObjectType of object
     * @psalm-suppress MixedInferredReturnType, InvalidReturnType, MixedMethodCall
     */
    public function createProxy(RpcClient $rpcClient, string $interfaceClassName): Promise
    {
        return call(function () use ($rpcClient, $interfaceClassName) {
            /** @psalm-var class-string<RemoteObjectType> $proxyObjectFullClassName */
            $proxyObjectFullClassName = RpcProxyClassNameInflector::getProxyFullClassName($interfaceClassName);

            if (false === class_exists($proxyObjectFullClassName, false)) {
                yield $this->loadProxyClass($interfaceClassName);
            }

            if (false === class_exists($proxyObjectFullClassName, false)) {
                throw new RpcException('Class loading failed.');
            }

            /** @psalm-return RemoteObjectType */
            return new $proxyObjectFullClassName($rpcClient);
        });
    }

    /**
     * @param class-string $interfaceClassName
     * @return Promise<void>
     * @psalm-suppress UnresolvableInclude
     */
    protected function loadProxyClass(string $interfaceClassName): Promise
    {
        return call(function () use ($interfaceClassName) {
            $interfaceReflectionClass = new ReflectionClass($interfaceClassName);
            $interfaceFileContent = yield $this->getInterfaceFileContent($interfaceReflectionClass);
            $packageVersionReference = $this->getPackageVersionReference();
            $proxyObjectClassFileName = RpcProxyClassNameInflector::getProxyFileName(
                $interfaceClassName,
                $interfaceFileContent,
                $packageVersionReference
            );
            $proxyObjectsDirPath = yield $this->proxyClassFileLocator->getProxyObjectsDirPath();
            $proxyObjectClassFileFullName = $proxyObjectsDirPath . DIRECTORY_SEPARATOR . $proxyObjectClassFileName;
            $filesystem = filesystem();

            if (false === yield $filesystem->isFile($proxyObjectClassFileFullName)) {
                yield $this->proxyObjectGenerator->generate(
                    $interfaceReflectionClass,
                    $proxyObjectClassFileFullName
                );
            }

            if (false === yield $filesystem->isFile($proxyObjectClassFileFullName)) {
                throw new RpcException('The class cannot be loaded because the class file does not exist.');
            }

            require_once $proxyObjectClassFileFullName;
        });
    }

    /**
     * @param ReflectionClass $interfaceReflectionClass
     * @return Promise<string>
     * @throws RpcException
     */
    protected function getInterfaceFileContent(ReflectionClass $interfaceReflectionClass): Promise
    {
        $interfaceFileName = $interfaceReflectionClass->getFileName();

        if ($interfaceFileName === false) {
            throw new RpcException('Failed to get file name for interface: ' . $interfaceReflectionClass->name);
        }

        return read($interfaceFileName);
    }

    /**
     * @return string
     * @throws RpcException
     */
    protected function getPackageVersionReference(): string
    {
        if (\is_null($this->packageVersionReference)) {
            $packageVersionReference = InstalledVersions::getReference('makaronnik/amphp-rpc');

            if (\is_null($packageVersionReference)) {
                throw new RpcException('Failed to get package version reference');
            }

            $this->packageVersionReference = $packageVersionReference;
        }

        return $this->packageVersionReference;
    }
}
