<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Bitcoin;

use InvalidArgumentException;

final readonly class BitcoinAmount
{
    private const int DECIMALS = 8;

    private function __construct(public string $satoshis) {}

    public static function fromDecimal(string $decimal): self
    {
        if (1 !== preg_match('/^(?<sign>-?)(?<whole>0|[1-9][0-9]*)(?:\.(?<fraction>[0-9]{1,8}))?$/', $decimal, $matches)) {
            throw new InvalidArgumentException(sprintf('Invalid Bitcoin decimal amount "%s".', $decimal));
        }

        $fraction = str_pad($matches['fraction'] ?? '', self::DECIMALS, '0');
        $units    = ltrim($matches['whole'] . $fraction, '0');
        $units    = '' === $units ? '0' : $units;

        return new self('-' === $matches['sign'] && '0' !== $units ? '-' . $units : $units);
    }

    public static function fromSatoshis(string $satoshis): self
    {
        if (1 !== preg_match('/^-?(?:0|[1-9][0-9]*)$/', $satoshis)) {
            throw new InvalidArgumentException(sprintf('Invalid satoshi amount "%s".', $satoshis));
        }

        return new self($satoshis);
    }

    public function decimal(): string
    {
        $negative = str_starts_with($this->satoshis, '-');
        $digits   = $negative ? substr($this->satoshis, 1) : $this->satoshis;
        $padded   = str_pad($digits, self::DECIMALS + 1, '0', STR_PAD_LEFT);
        $whole    = substr($padded, 0, -self::DECIMALS);
        $fraction = rtrim(substr($padded, -self::DECIMALS), '0');
        $decimal  = $whole . ('' === $fraction ? '' : '.' . $fraction);

        return $negative && '0' !== $digits ? '-' . $decimal : $decimal;
    }
}
