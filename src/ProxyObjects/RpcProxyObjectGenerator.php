<?php

namespace Makaronnik\Rpc\ProxyObjects;

use Amp\Promise;
use ReflectionClass;
use RuntimeException;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\TypeGenerator;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\PropertyGenerator;
use Laminas\Code\Generator\AbstractMemberGenerator;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassFileLocator;
use Makaronnik\Rpc\ProxyObjects\Utils\RpcProxyClassNameInflector;
use Makaronnik\Rpc\Traits\CheckRpsRemoteInterfaceMethodsSignatureTrait;

use function Amp\call;
use function Amp\File\filesystem;

final class RpcProxyObjectGenerator
{
    use CheckRpsRemoteInterfaceMethodsSignatureTrait;

    /**
     * @param RpcProxyClassFileLocator $proxyClassFileLocator
     */
    public function __construct(
        protected RpcProxyClassFileLocator $proxyClassFileLocator
    ) {
    }

    /**
     * @param ReflectionClass $interfaceReflectionClass
     * @param string $generatedProxyObjectClassFileFullName
     * @return Promise
     */
    public function generate(
        ReflectionClass $interfaceReflectionClass,
        string $generatedProxyObjectClassFileFullName
    ): Promise {
        return call(function () use ($interfaceReflectionClass, $generatedProxyObjectClassFileFullName) {
            $interfaceClassName = $interfaceReflectionClass->getName();

            if (false === $interfaceReflectionClass->isInterface()) {
                throw new RuntimeException(sprintf(
                    'Can`t create proxy object for %1$s because %1$s is not an interface',
                    $interfaceClassName
                ));
            }

            $this->checkMethodsSignature($interfaceReflectionClass, $interfaceClassName);

            $generatedProxyClassName = RpcProxyClassNameInflector::getProxyShortClassName($interfaceClassName);

            $classGenerator = new ClassGenerator(
                name: $generatedProxyClassName,
                namespaceName: RpcProxyClassNameInflector::getNamespace(),
                flags: ClassGenerator::FLAG_FINAL,
                extends: BaseProxyObject::class
            );

            $availableMethods = [];

            foreach ($interfaceReflectionClass->getMethods() as $method) {
                $availableMethods[] = $method->getName();
            }

            $classGenerator->addPropertyFromGenerator(
                new PropertyGenerator(
                    name: 'availableMethods',
                    defaultValue: $availableMethods,
                    flags: AbstractMemberGenerator::FLAG_PROTECTED,
                    type: TypeGenerator::fromTypeString('array')
                )
            );

            $classGenerator->addMethod(
                name: 'getAvailableMethods',
                flags: AbstractMemberGenerator::FLAG_PROTECTED,
                body: 'return $this->availableMethods;'
            );

            $classGenerator->addMethod(
                name: 'getRemoteObjectInterfaceClassName',
                flags: AbstractMemberGenerator::FLAG_PROTECTED,
                body: "return '" . $interfaceClassName . "';"
            );

            $fileGenerator = new FileGenerator();

            $fileGenerator->setClass($classGenerator);

            $generatedProxyObjectClassFileContent = $fileGenerator->generate();

            yield $this->writeGeneratedProxyClassFile(
                $generatedProxyObjectClassFileContent,
                $generatedProxyObjectClassFileFullName
            );
        });
    }

    /**
     * @param string $generatedProxyObjectClassFileContent
     * @param string $generatedProxyObjectClassFileFullName
     * @return Promise
     */
    protected function writeGeneratedProxyClassFile(
        string $generatedProxyObjectClassFileContent,
        string $generatedProxyObjectClassFileFullName
    ): Promise {
        return call(function () use (
            $generatedProxyObjectClassFileContent,
            $generatedProxyObjectClassFileFullName
        ) {
            $filesystem = filesystem();

            if (yield $filesystem->isFile($generatedProxyObjectClassFileFullName)) {
                yield $filesystem->deleteFile($generatedProxyObjectClassFileFullName);
            }

            yield $filesystem->write($generatedProxyObjectClassFileFullName, $generatedProxyObjectClassFileContent);
        });
    }
}
