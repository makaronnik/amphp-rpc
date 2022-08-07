<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Status;
use Amp\Http\Server\Response;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;

final class ResponseBuilder
{
    protected string $content = '';
    protected int $statusCode = Status::OK;
    protected string $statusReason = '';

    /** @var string[] */
    protected array $headers = [];

    /**
     * @param RpcResponseType $responseType
     */
    public function __construct(protected RpcResponseType $responseType)
    {
    }

    /**
     * @param string $content
     * @return $this
     */
    public function withContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param int $code
     * @param string $reason
     * @return $this
     */
    public function withStatus(int $code, string $reason = ''): self
    {
        $this->statusCode = $code;
        $this->statusReason = $reason;

        return $this;
    }

    /**
     * @param RpcMessageHeader $name
     * @param string $value
     * @return $this
     */
    public function withHeader(RpcMessageHeader $name, string $value): self
    {
        $this->headers += [$name->value => $value];

        return $this;
    }

    /**
     * @return $this
     */
    public function withSerializedContent(): self
    {
        $this->withHeader(RpcMessageHeader::WithSerializedContent, 'true');

        return $this;
    }

    /**
     * @return Response
     */
    public function build(): Response
    {
        $headers = $this->headers += [RpcMessageHeader::ResponseType->value => $this->responseType->value];

        $response = new Response(
            code: $this->statusCode,
            headers: $headers,
            stringOrStream: $this->content
        );

        if ($this->statusReason) {
            $response->setStatus($this->statusCode, $this->statusReason);
        }

        return $response;
    }
}
