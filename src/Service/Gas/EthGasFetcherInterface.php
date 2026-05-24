<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Gas;

interface EthGasFetcherInterface
{
    /**
     * @return null|numeric-string decimal wei (`gas_used * effective_gas_price`)
     */
    public function fetchWei(string $txHash): ?string;
}
