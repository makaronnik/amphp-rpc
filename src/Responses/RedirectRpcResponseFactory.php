<?php

namespace Makaronnik\Rpc\Responses;

use Amp\Http\Server\Response;
use Makaronnik\Rpc\Enums\RpcResponseType;
use Makaronnik\Rpc\Enums\RpcMessageHeader;

final class RedirectRpcResponseFactory implements ResponseFactory
{
    /**
     * @param string $redirectToHostOrIp
     * @param string|null $redirectToPath
     * @param int|null $redirectToPort
     * @param bool $directDirection
     */
    public function __construct(
        protected string  $redirectToHostOrIp,
        protected ?string $redirectToPath = null,
        protected ?int    $redirectToPort = null,
        protected bool    $directDirection = false
    ) {
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        $builder = (new ResponseBuilder(RpcResponseType::Redirect))
            ->withHeader(RpcMessageHeader::RedirectToHostOrIp, $this->redirectToHostOrIp);


        if ($this->redirectToPath) {
            $builder->withHeader(RpcMessageHeader::RedirectToPath, $this->redirectToPath);
        }

        if ($this->redirectToPort) {
            $builder->withHeader(RpcMessageHeader::RedirectToPort, (string) $this->redirectToPort);
        }

        if ($this->directDirection) {
            $builder->withHeader(RpcMessageHeader::DirectDirection, 'true');
        }

        return $builder->build();
    }
}
