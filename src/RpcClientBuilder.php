<?php

namespace Makaronnik\Rpc;

use cash\LRUCache;
use Amp\Cache\ArrayCache;
use Amp\Http\Client\HttpClient;
use Amp\Serialization\Serializer;
use Makaronnik\Rpc\Dns\DnsResolver;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Serialization\NativeSerializer;
use Makaronnik\Rpc\Dns\DnsConfigLoader;

final class RpcClientBuilder
{
    protected const DEFAULT_URI_SCHEME = 'http';
    protected const DEFAULT_RPC_SERVER_PORT = 8080;
    protected const DEFAULT_HTTP_CLIENT_RETRY_LIMIT = 0;
    protected const DEFAULT_DNS_RESOLVER_TIMEOUT_IN_MS = 100;
    protected const DEFAULT_DNS_RESOLVER_ATTEMPTS = 2;
    protected const DEFAULT_DNS_RESOLVER_CACHE_GC_INTERVAL_IN_MS = 5000;
    protected const DEFAULT_DNS_RESOLVER_CACHE_MAX_SIZE = 5000;
    protected const DEFAULT_REQUEST_TO_URI_CACHE_MAX_SIZE = 100;

    protected ?Serializer $serializer = null;
    protected ?HttpClient $httpClient = null;
    protected ?DnsResolver $dnsResolver = null;
    protected ?LRUCache $requestToUriCache = null;
    protected ?RpcResponseHandlerInterface $responseHandler = null;
    protected ?int $targetEntityIdParamPosition = null;
    protected ?int $redirectsLimit = null;
    protected ?int $retryingLimit = null;
    protected ?int $retryingDelayInMs = null;
    protected ?int $requestTimeoutInMs = null;

    /**
     * @param string $rpcServerHostnameOrIp
     * @param string $uriScheme
     * @param int $rpcServerPort
     */
    public function __construct(
        protected string $rpcServerHostnameOrIp,
        protected string $uriScheme = self::DEFAULT_URI_SCHEME,
        protected int $rpcServerPort = self::DEFAULT_RPC_SERVER_PORT
    ) {
    }

    /**
     * @param Serializer $serializer
     * @return $this
     */
    public function withSerializer(Serializer $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * @param HttpClient $httpClient
     * @return $this
     */
    public function withHttpClient(HttpClient $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * @param DnsResolver $dnsResolver
     * @return $this
     */
    public function withDnsResolver(DnsResolver $dnsResolver): self
    {
        $this->dnsResolver = $dnsResolver;

        return $this;
    }

    /**
     * @param LRUCache $requestToUriCache
     * @return $this
     */
    public function withRequestToUriCache(LRUCache $requestToUriCache): self
    {
        $this->requestToUriCache = $requestToUriCache;

        return $this;
    }

    /**
     * @param RpcResponseHandlerInterface $responseHandler
     * @return $this
     */
    public function withResponseHandler(RpcResponseHandlerInterface $responseHandler): self
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }

    /**
     * @param int $targetEntityIdParamPosition
     * @return $this
     */
    public function withTargetEntityIdParamPosition(int $targetEntityIdParamPosition): self
    {
        $this->targetEntityIdParamPosition = $targetEntityIdParamPosition;

        return $this;
    }

    /**
     * @param int $redirectsLimit
     * @return $this
     */
    public function withRedirectsLimit(int $redirectsLimit): self
    {
        $this->redirectsLimit = $redirectsLimit;

        return $this;
    }

    /**
     * @param int $retryingLimit
     * @return $this
     */
    public function withRetryingLimit(int $retryingLimit): self
    {
        $this->retryingLimit = $retryingLimit;

        return $this;
    }

    /**
     * @param int $retryingDelayInMs
     * @return $this
     */
    public function withRetryingDelayInMs(int $retryingDelayInMs): self
    {
        $this->retryingDelayInMs = $retryingDelayInMs;

        return $this;
    }

    /**
     * @param int $requestTimeoutInMs
     * @return $this
     */
    public function withRequestTimeoutInMs(int $requestTimeoutInMs): self
    {
        $this->requestTimeoutInMs = $requestTimeoutInMs;

        return $this;
    }

    /**
     * @return RpcClient
     */
    public function build(): RpcClient
    {
        $serializer = $this->serializer ?? new NativeSerializer();
        $responseHandler = $this->responseHandler ?? new RpcResponseHandler();
        $httpClient = $this->httpClient ?? (new HttpClientBuilder())
                ->retry(self::DEFAULT_HTTP_CLIENT_RETRY_LIMIT)
                ->build();
        $dnsResolver = $this->dnsResolver ?? new DnsResolver(
            configLoader: new DnsConfigLoader(
                timeoutInMs: self::DEFAULT_DNS_RESOLVER_TIMEOUT_IN_MS,
                attempts:    self::DEFAULT_DNS_RESOLVER_ATTEMPTS
            ),
            cache: new ArrayCache(
                gcInterval: self::DEFAULT_DNS_RESOLVER_CACHE_GC_INTERVAL_IN_MS,
                maxSize:    self::DEFAULT_DNS_RESOLVER_CACHE_MAX_SIZE
            )
        );
        $requestToUriCache = $this->requestToUriCache ?? new LRUCache(self::DEFAULT_REQUEST_TO_URI_CACHE_MAX_SIZE);
        $redirectsLimit = $this->redirectsLimit ?? RpcClientConfig::DEFAULT_REDIRECTS_LIMIT;
        $retryingLimit = $this->retryingLimit ?? RpcClientConfig::DEFAULT_RETRYING_LIMIT;
        $retryingDelayInMs = $this->retryingDelayInMs ?? RpcClientConfig::DEFAULT_RETRYING_DELAY_IN_MS;

        $config = new RpcClientConfig(
            redirectsLimit:              $redirectsLimit,
            retryingLimit:               $retryingLimit,
            retryingDelayInMs:           $retryingDelayInMs,
            requestTimeoutInMs:          $this->requestTimeoutInMs,
            targetEntityIdParamPosition: $this->targetEntityIdParamPosition
        );

        return new RpcClient(
            uri:               $this->composeUri(),
            config:            $config,
            responseHandler:   $responseHandler,
            serializer:        $serializer,
            httpClient:        $httpClient,
            dnsResolver:       $dnsResolver,
            requestToUriCache: $requestToUriCache
        );
    }

    /**
     * @return string
     */
    protected function composeUri(): string
    {
        return strtolower(sprintf(
            '%s://%s:%s',
            $this->uriScheme,
            $this->rpcServerHostnameOrIp,
            $this->rpcServerPort
        ));
    }
}
