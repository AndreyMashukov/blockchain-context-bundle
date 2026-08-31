<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinProcessedPsbt
{
    public function __construct(public string $psbt, public bool $complete) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(BitcoinRpcValue::string($row, 'psbt'), BitcoinRpcValue::bool($row, 'complete'));
    }
}
