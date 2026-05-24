<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class DepositTxPayload
{
    /**
     * @param 'evm-erc20'|'evm-native'|'ton-jetton'|'ton-native' $kind
     * @param array<string, mixed>                               $payload
     */
    public function __construct(
        public string $kind,
        public array $payload,
    ) {}
}
