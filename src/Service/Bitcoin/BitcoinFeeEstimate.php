<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinFeeEstimate
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public ?string $btcPerKvB, public int $blocks, public array $errors) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $errors = [];
        $rawErrors = $row['errors'] ?? [];
        if (!is_array($rawErrors)) {
            throw new BitcoinRpcException('Bitcoin Core field "errors" must be a list.');
        }
        foreach ($rawErrors as $error) {
            if (is_string($error)) {
                $errors[] = $error;
            }
        }

        return new self(
            BitcoinRpcValue::nullableString($row, 'feerate'),
            BitcoinRpcValue::int($row, 'blocks'),
            $errors,
        );
    }
}
