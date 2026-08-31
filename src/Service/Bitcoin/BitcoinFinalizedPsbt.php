<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinFinalizedPsbt
{
    public function __construct(public ?string $hex, public bool $complete) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(BitcoinRpcValue::nullableString($row, 'hex'), BitcoinRpcValue::bool($row, 'complete'));
    }
}
