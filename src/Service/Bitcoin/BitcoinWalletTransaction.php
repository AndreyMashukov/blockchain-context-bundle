<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinWalletTransaction
{
    public function __construct(
        public string $txid,
        public BitcoinAmount $amount,
        public BitcoinAmount $fee,
        public int $confirmations,
        public ?string $blockHash,
        public string $hex,
        public ?string $replacedByTxid,
        public ?string $replacesTxid,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $fee = BitcoinRpcValue::nullableString($row, 'fee') ?? '0';

        return new self(
            BitcoinRpcValue::string($row, 'txid'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'amount')),
            BitcoinAmount::fromDecimal($fee),
            BitcoinRpcValue::int($row, 'confirmations'),
            BitcoinRpcValue::nullableString($row, 'blockhash'),
            BitcoinRpcValue::string($row, 'hex'),
            BitcoinRpcValue::nullableString($row, 'replaced_by_txid'),
            BitcoinRpcValue::nullableString($row, 'replaces_txid'),
        );
    }
}
