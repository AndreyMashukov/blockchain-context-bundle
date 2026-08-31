<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinFundedPsbt
{
    public function __construct(public string $psbt, public BitcoinAmount $fee, public int $changePosition) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            BitcoinRpcValue::string($row, 'psbt'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'fee')),
            BitcoinRpcValue::int($row, 'changepos'),
        );
    }
}
