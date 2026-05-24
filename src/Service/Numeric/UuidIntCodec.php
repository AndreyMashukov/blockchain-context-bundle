<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Numeric;

use InvalidArgumentException;

final readonly class UuidIntCodec
{
    /**
     * @throws InvalidArgumentException on malformed UUID input
     */
    public function encode(string $uuid): string
    {
        $hex = str_replace('-', '', strtolower($uuid));
        if (1 !== preg_match('/^[0-9a-f]{32}$/', $hex)) {
            throw new InvalidArgumentException(sprintf('UuidIntCodec::encode: malformed UUID %s', $uuid));
        }

        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /**
     * @throws InvalidArgumentException on non-numeric / overflow input
     */
    public function decode(string $decimal): string
    {
        if ('' === $decimal || 1 !== preg_match('/^\d+$/', $decimal)) {
            throw new InvalidArgumentException(sprintf('UuidIntCodec::decode: non-numeric input %s', $decimal));
        }
        $hex = gmp_strval(gmp_init($decimal, 10), 16);
        if (\strlen($hex) > 32) {
            throw new InvalidArgumentException(sprintf('UuidIntCodec::decode: value exceeds 128 bits (%s)', $decimal));
        }
        $hex = str_pad($hex, 32, '0', \STR_PAD_LEFT);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
