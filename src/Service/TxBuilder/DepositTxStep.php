<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\TxBuilder;

final readonly class DepositTxStep
{
    /**
     * @param 'evm-approve'|'evm-deposit-native'|'evm-deposit-erc20'|'ton-deposit-native'|'ton-deposit-jetton'|'done' $kind
     * @param array<string, mixed>|null                                                                              $tx   EVM: {to, data, value, chainId}; TON: {address, amount, payload}; null when done
     */
    public function __construct(
        public string $kind,
        public ?string $buttonLabel,
        public ?array $tx,
        public bool $done,
    ) {}

    public static function done(): self
    {
        return new self(kind: 'done', buttonLabel: null, tx: null, done: true);
    }
}
