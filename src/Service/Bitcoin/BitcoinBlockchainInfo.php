<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinBlockchainInfo
{
    public function __construct(
        public string $chain,
        public int $blocks,
        public int $headers,
        public string $bestBlockHash,
        public bool $initialBlockDownload,
        public bool $pruned,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            BitcoinRpcValue::string($row, 'chain'),
            BitcoinRpcValue::int($row, 'blocks'),
            BitcoinRpcValue::int($row, 'headers'),
            BitcoinRpcValue::string($row, 'bestblockhash'),
            BitcoinRpcValue::bool($row, 'initialblockdownload'),
            BitcoinRpcValue::bool($row, 'pruned'),
        );
    }
}
