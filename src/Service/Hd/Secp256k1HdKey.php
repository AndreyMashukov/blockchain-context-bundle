<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use GMP;
use Amashukov\Secp256k1\Secp256k1;
use InvalidArgumentException;
use RuntimeException;

final readonly class Secp256k1HdKey
{
    private function __construct(
        public string $privateKey,
        public string $chainCode,
    ) {}

    public static function fromSeed(string $seed): self
    {
        $digest = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
        $key    = substr($digest, 0, 32);
        self::assertValidKey(gmp_import($key));

        return new self($key, substr($digest, 32));
    }

    public function derive(int $index): self
    {
        if ($index < 0 || $index > 0xFFFFFFFF) {
            throw new InvalidArgumentException(sprintf('BIP32 child index must be 0..4294967295, got %d', $index));
        }

        $data   = $index >= HdPath::HARDENED ? "\x00" . $this->privateKey : $this->compressedPublicKey();
        $digest = hash_hmac('sha512', $data . pack('N', $index), $this->chainCode, true);
        $tweak  = gmp_import(substr($digest, 0, 32));
        if (gmp_cmp($tweak, Secp256k1::n()) >= 0) {
            throw new RuntimeException(sprintf('BIP32 child %d is invalid, use the next index', $index));
        }

        $child = gmp_mod(gmp_add($tweak, gmp_import($this->privateKey)), Secp256k1::n());
        self::assertValidKey($child);

        return new self(str_pad(gmp_export($child), 32, "\x00", \STR_PAD_LEFT), substr($digest, 32));
    }

    public function derivePath(string $path): self
    {
        $key = $this;
        foreach (HdPath::parse($path) as $index) {
            $key = $key->derive($index);
        }

        return $key;
    }

    public function compressedPublicKey(): string
    {
        [$x, $y] = $this->publicPoint();

        return (0 === gmp_cmp(gmp_mod($y, 2), 0) ? "\x02" : "\x03") . $this->bytes32($x);
    }

    public function uncompressedPublicKey(): string
    {
        [$x, $y] = $this->publicPoint();

        return $this->bytes32($x) . $this->bytes32($y);
    }

    /**
     * @return array{0: GMP, 1: GMP}
     */
    private function publicPoint(): array
    {
        $g     = Secp256k1::g();
        $point = Secp256k1::scalarMulG(gmp_import($this->privateKey), Secp256k1::p(), $g['x'], $g['y']);
        if (null === $point) {
            throw new RuntimeException('The private key derives the point at infinity');
        }

        return $point;
    }

    private function bytes32(GMP $value): string
    {
        return str_pad(gmp_export($value), 32, "\x00", \STR_PAD_LEFT);
    }

    private static function assertValidKey(GMP $key): void
    {
        if (0 === gmp_cmp($key, 0) || gmp_cmp($key, Secp256k1::n()) >= 0) {
            throw new RuntimeException('The derived secp256k1 key is outside the curve order');
        }
    }
}
