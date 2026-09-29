<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Helper;

use LogicException;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingHttpClient implements ClientInterface
{
    public ?RequestInterface $request = null;

    /**
     * @var list<string>
     */
    private array $bodies;

    /**
     * @param string|list<string> $body
     */
    public function __construct(string|array $body)
    {
        $this->bodies = is_string($body) ? [$body] : $body;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        $body = array_shift($this->bodies);
        if (null === $body) {
            throw new LogicException('No queued Bitcoin RPC response.');
        }

        return new Response(200, [], $body);
    }
}
