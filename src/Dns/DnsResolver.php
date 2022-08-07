<?php

namespace Makaronnik\Rpc\Dns;

use Error;
use Amp\Promise;
use Amp\Dns\Record;
use Amp\Cache\Cache;
use Amp\Dns\Resolver;
use Amp\Cache\ArrayCache;
use Amp\Dns\ConfigLoader;
use Amp\Dns\DnsException;
use Amp\Dns\ConfigException;
use Amp\Dns\NoRecordException;
use Amp\Dns\Rfc1035StubResolver;

use function Amp\call;

final class DnsResolver implements Resolver
{
    protected Resolver $resolver;

    /**
     * @param ConfigLoader $configLoader
     * @param Cache $cache
     * @param Resolver|null $resolver
     */
    public function __construct(
        protected ConfigLoader $configLoader,
        public readonly Cache $cache = new ArrayCache(5000 /* default gc interval */, 256 /* size */),
        ?Resolver $resolver = null
    ) {
        $this->resolver = $resolver ?? new Rfc1035StubResolver($this->cache, $this->configLoader);
    }

    /**
     * Resolve and return first `Record::A`.
     *
     * @param string $name
     * @return Promise<string>
     * @throws DnsException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function getFirstARecord(string $name): Promise
    {
        return call(function () use ($name) {
            /** @var array $records */
            $records = yield $this->resolve($name, Record::A);

            if (isset($records[0]) && $records[0] instanceof Record) {
                return $records[0]->getValue();
            }

            throw new DnsException(sprintf("Failed to get ip from hostname '%s'", $name));
        });
    }

    /**
     * Resolves a hostname name to an IP address [hostname as defined by RFC 3986].
     *
     * Upon success the returned promise resolves to an array of Record objects.
     *
     * A null $ttl value indicates the DNS name was resolved from the cache or the local hosts file.
     *
     * @param string $name The hostname to resolve.
     * @param int|null $typeRestriction Optional type restriction to `Record::A` or `Record::AAAA`, otherwise `null`.
     *
     * @return Promise
     *
     * @throws Error
     * @throws DnsException
     * @throws ConfigException
     * @throws NoRecordException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function resolve(string $name, int $typeRestriction = null): Promise
    {
        return $this->resolver->resolve($name, $typeRestriction);
    }

    /**
     * Query specific DNS records.
     *
     * Upon success the returned promise resolves to an array of Record objects.
     *
     * @param string $name Record to question, A, AAAA and PTR queries are automatically normalized.
     * @param int $type Use constants of Amp\Dns\Record.
     *
     * @return Promise
     *
     * @throws Error
     * @throws DnsException
     * @throws ConfigException
     * @throws NoRecordException
     * @noinspection PhpDocRedundantThrowsInspection
     */
    public function query(string $name, int $type): Promise
    {
        return $this->resolver->query($name, $type);
    }
}
