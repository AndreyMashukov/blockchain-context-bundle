<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use InvalidArgumentException;
use RuntimeException;

final readonly class Bip39Mnemonic
{
    private const array ENTROPY_BYTES = [16, 20, 24, 28, 32];

    private const string WORDLIST = __DIR__ . '/../../../Resources/bip39/english.txt';

    private function __construct() {}

    public static function generate(int $words = 24): string
    {
        $bytes = intdiv($words * 11 * 32, 33 * 8);
        if (!\in_array($bytes, self::ENTROPY_BYTES, true) || $bytes * 8 * 33 !== $words * 11 * 32) {
            throw new InvalidArgumentException(sprintf('A BIP39 mnemonic has 12, 15, 18, 21 or 24 words, got %d', $words));
        }

        return self::fromEntropy(random_bytes($bytes));
    }

    public static function fromEntropy(string $entropy): string
    {
        $length = \strlen($entropy);
        if (!\in_array($length, self::ENTROPY_BYTES, true)) {
            throw new InvalidArgumentException(sprintf('BIP39 entropy is 16, 20, 24, 28 or 32 bytes, got %d', $length));
        }

        $bits     = self::bits($entropy) . substr(self::bits(hash('sha256', $entropy, true)), 0, intdiv($length * 8, 32));
        $wordlist = self::wordlist();
        $words    = [];
        foreach (str_split($bits, 11) as $index) {
            $words[] = $wordlist[(int) bindec($index)];
        }

        return implode(' ', $words);
    }

    public static function isValid(string $mnemonic): bool
    {
        $words = preg_split('/\s+/', trim($mnemonic), -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $words || !\in_array(\count($words) * 11 * 32 / 33 / 8, self::ENTROPY_BYTES, false)) {
            return false;
        }

        $positions = array_flip(self::wordlist());
        $bits      = '';
        foreach ($words as $word) {
            if (!isset($positions[$word])) {
                return false;
            }
            $bits .= str_pad(decbin($positions[$word]), 11, '0', \STR_PAD_LEFT);
        }

        $entropyBits = intdiv(\strlen($bits) * 32, 33);
        $entropy     = implode('', array_map(static fn(string $byte): string => \chr((int) bindec($byte)), str_split(substr($bits, 0, $entropyBits), 8)));

        return self::fromEntropy($entropy) === implode(' ', $words);
    }

    private static function bits(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(\ord($byte)), 8, '0', \STR_PAD_LEFT);
        }

        return $bits;
    }

    /**
     * @return list<string>
     */
    private static function wordlist(): array
    {
        $content = file_get_contents(self::WORDLIST);
        if (false === $content) {
            throw new RuntimeException('The BIP39 English wordlist is missing.');
        }

        $words = preg_split('/\R/', trim($content));
        if (false === $words || 2048 !== \count($words)) {
            throw new RuntimeException('The BIP39 English wordlist must hold 2048 words.');
        }

        return $words;
    }
}
