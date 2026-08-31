<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinOutpoint
{
    public function __construct(public string $txid, public int $vout) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(BitcoinRpcValue::string($row, 'txid'), BitcoinRpcValue::int($row, 'vout'));
    }

    /**
     * @return array{txid: string, vout: int}
     */
    public function toArray(): array
    {
        return ['txid' => $this->txid, 'vout' => $this->vout];
    }
}
