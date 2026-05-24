<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Numeric;

use InvalidArgumentException;

final class UsdtJettonDecimals
{
    public const int DECIMALS = 6;

    public const string ATOMIC_UNITS_PER_USDT = '1000000';

    public static function toAtomic(string $human): string
    {
        if ('' === $human || !is_numeric($human)) {
            throw new InvalidArgumentException(sprintf('UsdtJettonDecimals::toAtomic expects a numeric decimal string, got %s', var_export($human, true)));
        }

        return bcmul($human, self::ATOMIC_UNITS_PER_USDT, 0);
    }

    public static function fromAtomic(string $atomic): string
    {
        if ('' === $atomic || !ctype_digit($atomic)) {
            throw new InvalidArgumentException(sprintf('UsdtJettonDecimals::fromAtomic expects a non-negative integer string, got %s', var_export($atomic, true)));
        }

        return BcDecimal::trim(bcdiv($atomic, self::ATOMIC_UNITS_PER_USDT, self::DECIMALS));
    }
}
