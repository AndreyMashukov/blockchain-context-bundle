<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

use RuntimeException;

final class BitcoinRpcException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $rpcCode = null)
    {
        parent::__construct($message, $rpcCode ?? 0);
    }
}
