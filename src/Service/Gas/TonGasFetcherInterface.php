<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Gas;

interface TonGasFetcherInterface
{
    /**
     * @return null|numeric-string `in_msg.fwd_fee` (nanotons)
     */
    public function fetchFwdFeeNano(string $depositAddress, string $txHash): ?string;
}
