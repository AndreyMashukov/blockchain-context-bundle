<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinRawInput
{
    public function __construct(public ?BitcoinOutpoint $outpoint, public ?BitcoinAmount $amount, public ?string $address, public ?string $scriptPubKey) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        if (isset($row['coinbase'])) {
            return new self(null, null, null, null);
        }
        $outpoint = BitcoinOutpoint::fromArray($row);
        if (!isset($row['prevout'])) {
            return new self($outpoint, null, null, null);
        }
        $previous = BitcoinRpcValue::object($row, 'prevout');
        $script = BitcoinRpcValue::object($previous, 'scriptPubKey');
        $amount = BitcoinAmount::fromDecimal(BitcoinRpcValue::string($previous, 'value'));
        if (str_starts_with($amount->satoshis, '-')) {
            throw new BitcoinRpcException('A previous transaction output cannot have a negative amount.');
        }
        return new self($outpoint, $amount, BitcoinRpcValue::nullableString($script, 'address'), BitcoinRpcValue::string($script, 'hex'));
    }
}
