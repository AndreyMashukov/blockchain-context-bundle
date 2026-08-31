<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinAddressInfo
{
    public function __construct(
        public bool $isValid,
        public bool $isMine,
        public bool $isWatchOnly,
        public ?string $scriptPubKey,
        public ?string $descriptor,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            BitcoinRpcValue::bool($row, 'isvalid'),
            is_bool($row['ismine'] ?? null) ? $row['ismine'] : false,
            is_bool($row['iswatchonly'] ?? null) ? $row['iswatchonly'] : false,
            BitcoinRpcValue::nullableString($row, 'scriptPubKey'),
            BitcoinRpcValue::nullableString($row, 'desc'),
        );
    }
}
