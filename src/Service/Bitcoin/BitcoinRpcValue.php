<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinRpcValue
{
    /**
     * @param array<string, mixed> $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || null === $row[$key]) {
            return null;
        }

        return self::string($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function int(array $row, string $key): int
    {
        $value = self::string($row, $key);
        if (1 !== preg_match('/^-?[0-9]+$/', $value)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be an integer.', $key));
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;
        if (!is_bool($value)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be a boolean.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function object(array $row, string $key): array
    {
        return self::map($row[$key] ?? null, $key);
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be an object.', $field));
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must have string keys.', $field));
            }
            $map[$key] = $item;
        }

        return $map;
    }
}
