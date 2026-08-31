<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Helper;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingHttpClient implements ClientInterface
{
    public ?RequestInterface $request = null;

    public function __construct(private readonly string $body) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return new Response(200, [], $this->body);
    }
}
