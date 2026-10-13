<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinRawOutput
{
    public function __construct(public int $index, public BitcoinAmount $amount, public ?string $address, public string $scriptPubKey) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $script = BitcoinRpcValue::object($row, 'scriptPubKey');
        $amount = BitcoinAmount::fromDecimal(BitcoinRpcValue::string($row, 'value'));
        if (str_starts_with($amount->satoshis, '-')) {
            throw new BitcoinRpcException('A transaction output cannot have a negative amount.');
        }
        return new self(BitcoinRpcValue::int($row, 'n'), $amount, BitcoinRpcValue::nullableString($script, 'address'), BitcoinRpcValue::string($script, 'hex'));
    }
}
