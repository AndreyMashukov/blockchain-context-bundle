<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinWalletTransactionEntry
{
    public function __construct(
        public string $txid,
        public int $vout,
        public string $category,
        public ?string $address,
        public BitcoinAmount $amount,
        public int $confirmations,
        public ?string $blockHash,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            BitcoinRpcValue::string($row, 'txid'),
            BitcoinRpcValue::int($row, 'vout'),
            BitcoinRpcValue::string($row, 'category'),
            BitcoinRpcValue::nullableString($row, 'address'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'amount')),
            BitcoinRpcValue::int($row, 'confirmations'),
            BitcoinRpcValue::nullableString($row, 'blockhash'),
        );
    }
}
