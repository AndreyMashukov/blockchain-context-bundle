<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Gas;

final readonly class ZeroGasFetcher implements EthGasFetcherInterface, TonGasFetcherInterface
{
    public function fetchWei(string $txHash): string
    {
        return '0';
    }

    public function fetchFwdFeeNano(string $depositAddress, string $txHash): string
    {
        return '0';
    }
}
