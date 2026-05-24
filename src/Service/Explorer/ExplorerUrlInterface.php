<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Explorer;

interface ExplorerUrlInterface
{
    public function forTx(string $chain, string $txHashOrLt): ?string;

    public function forAddress(string $chain, string $address): ?string;
}
