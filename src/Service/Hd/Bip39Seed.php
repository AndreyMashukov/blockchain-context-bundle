<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use InvalidArgumentException;

final readonly class Bip39Seed
{
    private const array WORD_COUNTS = [12, 15, 18, 21, 24];

    private function __construct() {}

    public static function fromMnemonic(string $mnemonic, string $passphrase = ''): string
    {
        $words = preg_split('/\s+/', trim($mnemonic), -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $words || !\in_array(\count($words), self::WORD_COUNTS, true)) {
            throw new InvalidArgumentException(sprintf('A BIP39 mnemonic has 12, 15, 18, 21 or 24 words, got %d', false === $words ? 0 : \count($words)));
        }

        return hash_pbkdf2('sha512', implode(' ', $words), 'mnemonic' . $passphrase, 2048, 64, true);
    }
}
