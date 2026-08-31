<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinMempoolAcceptance
{
    public function __construct(
        public string $txid,
        public bool $allowed,
        public ?string $rejectReason,
        public ?int $vsize,
        public ?BitcoinAmount $baseFee,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $vsize = BitcoinRpcValue::nullableString($row, 'vsize');
        $fees  = is_array($row['fees'] ?? null) ? $row['fees'] : [];
        $base  = BitcoinRpcValue::nullableString($fees, 'base');

        return new self(
            BitcoinRpcValue::string($row, 'txid'),
            BitcoinRpcValue::bool($row, 'allowed'),
            BitcoinRpcValue::nullableString($row, 'reject-reason'),
            null === $vsize ? null : (int) $vsize,
            null === $base ? null : BitcoinAmount::fromDecimal($base),
        );
    }
}
