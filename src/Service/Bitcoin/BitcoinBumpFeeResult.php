<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinBumpFeeResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public string $psbt,
        public BitcoinAmount $originalFee,
        public BitcoinAmount $fee,
        public array $errors,
    ) {}

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
            BitcoinRpcValue::string($row, 'psbt'),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'origfee')),
            BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'fee')),
            $errors,
        );
    }
}
