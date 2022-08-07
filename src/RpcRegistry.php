<?php

namespace Makaronnik\Rpc;

use ReflectionClass;
use RuntimeException;
use ReflectionException;
use Makaronnik\Rpc\Traits\CheckRpsRemoteInterfaceMethodsSignatureTrait;

final class RpcRegistry
{
    use CheckRpsRemoteInterfaceMethodsSignatureTrait;

    /** @var array<string,class-string> $remoteObjectClasses [Remote interface lc name => Remote object class name] */
    protected array $remoteObjectClasses = [];

    /**
     * @param class-string $remoteInterfaceName
     * @param class-string $remoteObjectClassName
     * @return void
     * @throws RuntimeException
     * @throws ReflectionException
     */
    public function registerRemoteObject(string $remoteInterfaceName, string $remoteObjectClassName): void
    {
        $lowercaseRemoteInterfaceName = strtolower($remoteInterfaceName);

        if (isset($this->remoteObjectClasses[$lowercaseRemoteInterfaceName])) {
            throw new RuntimeException(sprintf(
                'Remote object implementing the interface %s is already registered.',
                $remoteInterfaceName
            ));
        }

        if (false === is_subclass_of($remoteObjectClassName, $remoteInterfaceName)) {
            throw new RuntimeException(sprintf(
                'Invalid remote object registration for %1$s, because %2$s does not implement %1$s.',
                $remoteInterfaceName,
                $remoteObjectClassName
            ));
        }

        $remoteInterfaceReflection = new ReflectionClass($remoteInterfaceName);

        if (false === $remoteInterfaceReflection->isInterface()) {
            throw new RuntimeException(sprintf(
                'Invalid remote object registration for %1$s, because %1$s is not an interface.',
                $remoteInterfaceName
            ));
        }

        $this->checkMethodsSignature($remoteInterfaceReflection, $remoteInterfaceName);

        $this->remoteObjectClasses[$lowercaseRemoteInterfaceName] = $remoteObjectClassName;
    }

    /**
     * @param string $remoteInterfaceName
     * @return class-string|null
     */
    public function getRemoteObjectClassName(string $remoteInterfaceName): ?string
    {
        return $this->remoteObjectClasses[strtolower($remoteInterfaceName)] ?? null;
    }

    /**
     * @return void
     */
    public function clearRegistry(): void
    {
        $this->remoteObjectClasses = [];
    }
}
