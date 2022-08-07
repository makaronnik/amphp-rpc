<?php

namespace Makaronnik\Rpc\Traits;

use Amp\Promise;
use ReflectionClass;
use RuntimeException;
use ReflectionNamedType;

trait CheckRpsRemoteInterfaceMethodsSignatureTrait
{
    /**
     * @param ReflectionClass $reflectionClass
     * @param string $interfaceClassName
     * @return void
     * @throws RuntimeException
     */
    protected function checkMethodsSignature(ReflectionClass $reflectionClass, string $interfaceClassName): void
    {
        foreach ($reflectionClass->getMethods() as $method) {
            $returnType = $method->getReturnType();
            $methodName = $method->getName();

            if (false === $returnType instanceof ReflectionNamedType) {
                throw new RuntimeException(sprintf(
                    'Failed to check return type for %s::%s().',
                    $interfaceClassName,
                    $methodName
                ));
            }

            if ($returnType->allowsNull() || $returnType->getName() !== Promise::class) {
                throw new RuntimeException(sprintf(
                    '%s::%s() must declare return type %s.',
                    $interfaceClassName,
                    $methodName,
                    Promise::class
                ));
            }
        }
    }
}
