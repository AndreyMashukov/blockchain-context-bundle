<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinListSinceBlock
{
    /**
     * @param list<BitcoinWalletTransactionEntry> $transactions
     * @param list<BitcoinWalletTransactionEntry> $removed
     */
    public function __construct(public array $transactions, public array $removed, public string $lastBlock) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            self::entries($row['transactions'] ?? null, 'transactions'),
            self::entries($row['removed'] ?? null, 'removed'),
            BitcoinRpcValue::string($row, 'lastblock'),
        );
    }

    /**
     * @return list<BitcoinWalletTransactionEntry>
     */
    private static function entries(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be a list.', $field));
        }

        $entries = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" contains an invalid entry.', $field));
            }
            $entries[] = BitcoinWalletTransactionEntry::fromArray(BitcoinRpcValue::map($row, $field . ' entry'));
        }

        return $entries;
    }
}
