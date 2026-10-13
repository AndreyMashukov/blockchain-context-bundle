<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

final readonly class BitcoinRawTransaction
{
    /**
     * @param list<BitcoinRawInput> $inputs
     * @param list<BitcoinRawOutput> $outputs
     */
    public function __construct(
        public string $txid,
        public ?string $blockHash,
        public ?bool $inActiveChain,
        public array $inputs,
        public array $outputs,
        public ?BitcoinAmount $fee,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $inputs = [];
        foreach (self::rows($row, 'vin') as $input) {
            $inputs[] = BitcoinRawInput::fromArray($input);
        }
        $outputs = [];
        foreach (self::rows($row, 'vout') as $output) {
            $outputs[] = BitcoinRawOutput::fromArray($output);
        }
        return new self(
            BitcoinRpcValue::string($row, 'txid'),
            BitcoinRpcValue::nullableString($row, 'blockhash'),
            array_key_exists('in_active_chain', $row) ? BitcoinRpcValue::bool($row, 'in_active_chain') : null,
            $inputs,
            $outputs,
            self::fee($inputs, $outputs, BitcoinRpcValue::nullableString($row, 'fee')),
        );
    }

    /**
     * @param list<BitcoinRawInput> $inputs
     * @param list<BitcoinRawOutput> $outputs
     */
    private static function fee(array $inputs, array $outputs, ?string $reported): ?BitcoinAmount
    {
        if ([] === $inputs || [] === $outputs) {
            return null;
        }
        $inputSum = '0';
        foreach ($inputs as $input) {
            if (null === $input->amount || null === $input->outpoint) {
                return null;
            }
            $inputSum = gmp_strval(gmp_add($inputSum, $input->amount->satoshis));
        }
        $outputSum = '0';
        foreach ($outputs as $output) {
            $outputSum = gmp_strval(gmp_add($outputSum, $output->amount->satoshis));
        }
        $fee = gmp_strval(gmp_sub($inputSum, $outputSum));
        if (str_starts_with($fee, '-') || (null !== $reported && BitcoinAmount::fromDecimal($reported)->satoshis !== $fee)) {
            throw new BitcoinRpcException('The complete transaction inputs and outputs disagree with its fee.');
        }
        return BitcoinAmount::fromSatoshis($fee);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(array $row, string $key): array
    {
        $values = $row[$key] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new BitcoinRpcException(sprintf('Bitcoin Core field "%s" must be a list.', $key));
        }
        return array_map(static fn(mixed $value): array => BitcoinRpcValue::map($value, $key), $values);
    }
}
