<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinWalletInfo
{
    public function __construct(
        public string $walletName,
        public int $walletVersion,
        public BitcoinAmount $balance,
        public BitcoinAmount $unconfirmedBalance,
        public int $txCount,
        public bool $privateKeysEnabled,
        public bool $descriptors,
        public ?int $unlockedUntil,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $unlocked = BitcoinRpcValue::nullableString($row, 'unlocked_until');

        return new self(
            BitcoinRpcValue::string($row, 'walletname'),
            BitcoinRpcValue::int($row, 'walletversion'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'balance')),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'unconfirmed_balance')),
            BitcoinRpcValue::int($row, 'txcount'),
            BitcoinRpcValue::bool($row, 'private_keys_enabled'),
            BitcoinRpcValue::bool($row, 'descriptors'),
            null === $unlocked ? null : (int) $unlocked,
        );
    }
}
