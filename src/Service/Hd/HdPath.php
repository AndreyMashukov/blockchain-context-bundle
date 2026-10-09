<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use InvalidArgumentException;

final readonly class HdPath
{
    public const int HARDENED = 0x80000000;

    private const int MAX_INDEX = 0x7FFFFFFF;

    private function __construct() {}

    /**
     * @return list<int>
     */
    public static function parse(string $path): array
    {
        $segments = explode('/', trim($path));
        if ('m' !== array_shift($segments)) {
            throw new InvalidArgumentException(sprintf('A derivation path starts with "m", got "%s"', $path));
        }

        $indexes = [];
        foreach ($segments as $segment) {
            if (1 !== preg_match("/^(\\d+)(['hH]?)$/", $segment, $match)) {
                throw new InvalidArgumentException(sprintf('Invalid derivation path segment "%s" in "%s"', $segment, $path));
            }
            $index = (int) $match[1];
            if ($index > self::MAX_INDEX || $match[1] !== (string) $index) {
                throw new InvalidArgumentException(sprintf('Derivation index out of range in "%s"', $path));
            }
            $indexes[] = '' === $match[2] ? $index : $index | self::HARDENED;
        }

        return $indexes;
    }

    public static function assertIndex(int $index): void
    {
        if ($index < 0 || $index > self::MAX_INDEX) {
            throw new InvalidArgumentException(sprintf('Derivation index must be 0..%d, got %d', self::MAX_INDEX, $index));
        }
    }
}
