<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service;

use Amashukov\BlockchainContextBundle\Service\PrivKeyEncrypter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PrivKeyEncrypterTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $enc       = $this->encrypter();
        $plaintext = random_bytes(32);

        $blob = $enc->encrypt($plaintext);

        self::assertNotSame($plaintext, $blob);
        self::assertSame($plaintext, $enc->decrypt($blob));
    }

    public function testEachEncryptUsesFreshNonce(): void
    {
        $enc = $this->encrypter();

        self::assertNotSame($enc->encrypt('secret'), $enc->encrypt('secret'));
    }

    public function testTamperedBlobFailsAuthentication(): void
    {
        $enc      = $this->encrypter();
        $blob     = $enc->encrypt('secret');
        $blob[20] = "\x00" === $blob[20] ? "\x01" : "\x00";

        $this->expectException(RuntimeException::class);
        $enc->decrypt($blob);
    }

    public function testShortBlobThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->encrypter()->decrypt('too-short');
    }

    public function testNon32ByteKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PrivKeyEncrypter(base64_encode('short-key'));
    }

    private function encrypter(): PrivKeyEncrypter
    {
        return new PrivKeyEncrypter(base64_encode(random_bytes(32)));
    }
}
