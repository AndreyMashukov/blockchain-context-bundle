<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Numeric;

final class BcDecimal
{
    /**
     * @return numeric-string
     */
    public static function trim(string $value): string
    {
        if ('' === $value || !is_numeric($value)) {
            return '0';
        }

        if (!str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');
        if ('' === $trimmed || !is_numeric($trimmed)) {
            return '0';
        }

        return $trimmed;
    }

    public static function normalize(string $value): string
    {
        if ('' === $value) {
            return '0';
        }

        if (!preg_match('/[eE]/', $value)) {
            return $value;
        }

        $expanded = sprintf('%.18F', (float) $value);

        return self::trim($expanded);
    }
}
