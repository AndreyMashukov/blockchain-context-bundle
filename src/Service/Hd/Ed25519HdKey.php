<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Hd;

use Amashukov\TonCrypto\KeyPair;
use InvalidArgumentException;

final readonly class Ed25519HdKey
{
    private function __construct(
        public string $privateKey,
        public string $chainCode,
    ) {}

    public static function fromSeed(string $seed): self
    {
        $digest = hash_hmac('sha512', $seed, 'ed25519 seed', true);

        return new self(substr($digest, 0, 32), substr($digest, 32));
    }

    public function derive(int $index): self
    {
        if ($index < HdPath::HARDENED || $index > 0xFFFFFFFF) {
            throw new InvalidArgumentException(sprintf('SLIP-0010 ed25519 derives hardened children only, got index %d', $index));
        }

        $digest = hash_hmac('sha512', "\x00" . $this->privateKey . pack('N', $index), $this->chainCode, true);

        return new self(substr($digest, 0, 32), substr($digest, 32));
    }

    public function derivePath(string $path): self
    {
        $key = $this;
        foreach (HdPath::parse($path) as $index) {
            $key = $key->derive($index);
        }

        return $key;
    }

    public function keyPair(): KeyPair
    {
        return KeyPair::fromSeed($this->privateKey);
    }
}
