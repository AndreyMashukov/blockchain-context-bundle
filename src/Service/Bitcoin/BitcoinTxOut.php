<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinTxOut
{
    public function __construct(
        public int $confirmations,
        public BitcoinAmount $value,
        public ?string $address,
        public string $scriptPubKey,
        public ?BitcoinTokenData $tokenData,
        public bool $coinbase,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $script = BitcoinRpcValue::object($row, 'scriptPubKey');

        return new self(
            BitcoinRpcValue::int($row, 'confirmations'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'value')),
            BitcoinRpcValue::nullableString($script, 'address'),
            BitcoinRpcValue::string($script, 'hex'),
            null,
            BitcoinRpcValue::bool($row, 'coinbase'),
        );
    }
}
