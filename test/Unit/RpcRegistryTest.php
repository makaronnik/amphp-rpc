<?php

namespace Makaronnik\Rpc\Test\Unit;

use Makaronnik\Rpc\RpcRegistry;
use PHPUnit\Framework\TestCase;
use Makaronnik\Rpc\Test\Support\SimpleCalc;
use Makaronnik\Rpc\Test\Support\SameSimpleCalc;
use Makaronnik\Rpc\Test\Support\InvalidRemoteObject;
use Makaronnik\Rpc\Test\Support\SimpleCalcInterface;
use Makaronnik\Rpc\Test\Support\InvalidRemoteObjectInterface;

/** @psalm-suppress MissingConstructor */
class RpcRegistryTest extends TestCase
{
    protected RpcRegistry $registry;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->registry = new RpcRegistry();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->registry->clearRegistry();
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testClearRegistry(): void
    {
        $remoteInterfaceName = SimpleCalcInterface::class;
        $remoteObjectClassName = SimpleCalc::class;

        $this->registry->registerRemoteObject(
            remoteInterfaceName: $remoteInterfaceName,
            remoteObjectClassName: $remoteObjectClassName
        );

        $this->registry->clearRegistry();

        $this->assertNull($this->registry->getRemoteObjectClassName($remoteInterfaceName));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testRegisterAndGetRemoteObjectSuccessfully(): void
    {
        $remoteInterfaceName = SimpleCalcInterface::class;
        $remoteObjectClassName = SimpleCalc::class;

        $this->registry->registerRemoteObject(
            remoteInterfaceName: $remoteInterfaceName,
            remoteObjectClassName: $remoteObjectClassName
        );

        $this->assertSame($remoteObjectClassName, $this->registry->getRemoteObjectClassName($remoteInterfaceName));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testThatAnInvalidRemoteObjectInterfaceMethodSignatureThrowsAnException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->registry->registerRemoteObject(
            remoteInterfaceName: InvalidRemoteObjectInterface::class,
            remoteObjectClassName: InvalidRemoteObject::class
        );
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testThatMultipleRegistrationOfTheSameRemoteObjectInterfaceThrowsAnException(): void
    {
        $remoteInterfaceName = SimpleCalcInterface::class;
        $remoteObjectClassName = SimpleCalc::class;

        $this->expectException(\RuntimeException::class);

        $this->registry->registerRemoteObject(
            remoteInterfaceName: $remoteInterfaceName,
            remoteObjectClassName: $remoteObjectClassName
        );

        $this->registry->registerRemoteObject(
            remoteInterfaceName: $remoteInterfaceName,
            remoteObjectClassName: $remoteObjectClassName
        );
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testThatRegistrationOfRemoteObjectInterfaceNotWithItsImplementationThrowsAnException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalcInterface::class,
            remoteObjectClassName: InvalidRemoteObject::class
        );
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testThatRegistrationOfRemoteObjectWithoutInterfaceThrowsAnException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->registry->registerRemoteObject(
            remoteInterfaceName: SimpleCalc::class,
            remoteObjectClassName: SameSimpleCalc::class
        );
    }
}
