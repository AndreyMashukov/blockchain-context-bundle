<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinUnspentOutput
{
    public function __construct(
        public BitcoinOutpoint $outpoint,
        public string $address,
        public BitcoinAmount $amount,
        public int $confirmations,
        public bool $spendable,
        public bool $solvable,
        public bool $safe,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            BitcoinOutpoint::fromArray($row),
            BitcoinRpcValue::string($row, 'address'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'amount')),
            BitcoinRpcValue::int($row, 'confirmations'),
            BitcoinRpcValue::bool($row, 'spendable'),
            BitcoinRpcValue::bool($row, 'solvable'),
            BitcoinRpcValue::bool($row, 'safe'),
        );
    }
}
