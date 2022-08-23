<?php

namespace Makaronnik\Rpc\Test\Integration;

use Amp\Dns\Record;
use Amp\PHPUnit\AsyncTestCase;
use Makaronnik\Rpc\Dns\DnsResolver;
use Makaronnik\Rpc\Dns\DnsConfigLoader;

/** @psalm-suppress MissingConstructor */
class DnsTest extends AsyncTestCase
{
    protected DnsConfigLoader $configLoader;
    protected DnsResolver $resolver;

    /**
     * @return void
     */
    public function setUpAsync(): void
    {
        $this->setTimeout(1000);
        $this->configLoader = new DnsConfigLoader(1000, 10);
        $this->resolver = new DnsResolver($this->configLoader);
    }

    /**
     * @return \Generator
     * @throws \Amp\Dns\ConfigException
     * @throws \Amp\Dns\DnsException
     * @throws \Amp\Dns\NoRecordException
     */
    public function testGetFirstARecord(): \Generator
    {
        $ip = yield $this->resolver->getFirstARecord('localhost');

        $this->assertSame('127.0.0.1', $ip);
    }

    /**
     * @return \Generator
     * @throws \Amp\Dns\ConfigException
     * @throws \Amp\Dns\DnsException
     * @throws \Amp\Dns\NoRecordException
     */
    public function testQuery(): \Generator
    {
        /** @var Record[] $records */
        $records = yield $this->resolver->query('localhost', Record::A);

        $this->assertSame('127.0.0.1', $records[0]->getValue());
    }

    /**
     * @return \Generator
     * @throws \Amp\Dns\ConfigException
     * @throws \Amp\Dns\DnsException
     * @throws \Amp\Dns\NoRecordException
     */
    public function testResolve(): \Generator
    {
        /** @var Record[] $records */
        $records = yield $this->resolver->resolve('localhost');

        $this->assertSame('127.0.0.1', $records[0]->getValue());
    }
}
