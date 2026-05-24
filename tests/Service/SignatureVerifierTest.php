<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service;

use Amashukov\BlockchainContextBundle\Service\SignatureVerifier;
use Amashukov\Keccak\Keccak;
use Amashukov\Secp256k1\Ecdsa;
use Amashukov\Secp256k1\Secp256k1;
use PHPUnit\Framework\TestCase;

final class SignatureVerifierTest extends TestCase
{
    private const string PRIV_HEX = '4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318';

    public function testVerifyEthRoundTripAcceptsValidSignature(): void
    {
        $message = 'Sign in to my dApp';
        $privKey = $this->bin(self::PRIV_HEX);
        $address = $this->ethAddressFor($privKey);

        $signature = $this->ethSign($message, $privKey);

        self::assertTrue((new SignatureVerifier())->verifyEth($message, $signature, $address));
    }

    public function testVerifyEthRejectsWrongAddress(): void
    {
        $message   = 'hello';
        $privKey   = $this->bin(self::PRIV_HEX);
        $signature = $this->ethSign($message, $privKey);

        self::assertFalse((new SignatureVerifier())->verifyEth($message, $signature, '0x0000000000000000000000000000000000000001'));
    }

    public function testVerifyEthRejectsTamperedMessage(): void
    {
        $privKey   = $this->bin(self::PRIV_HEX);
        $address   = $this->ethAddressFor($privKey);
        $signature = $this->ethSign('original', $privKey);

        self::assertFalse((new SignatureVerifier())->verifyEth('tampered', $signature, $address));
    }

    public function testVerifyEthRejectsMalformedSignature(): void
    {
        self::assertFalse((new SignatureVerifier())->verifyEth('hello', '0xdeadbeef', '0x0000000000000000000000000000000000000001'));
    }

    public function testVerifyTonRoundTrip(): void
    {
        $keyPair   = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $message   = 'ton-connect proof payload';

        $signature = base64_encode(sodium_crypto_sign_detached($message, $secretKey));

        self::assertTrue((new SignatureVerifier())->verifyTon($message, $signature, bin2hex($publicKey)));
    }

    public function testVerifyTonRejectsWrongMessage(): void
    {
        $keyPair   = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $secretKey = sodium_crypto_sign_secretkey($keyPair);

        $signature = base64_encode(sodium_crypto_sign_detached('signed', $secretKey));

        self::assertFalse((new SignatureVerifier())->verifyTon('different', $signature, bin2hex($publicKey)));
    }

    public function testVerifyTonRejectsMalformedSignature(): void
    {
        self::assertFalse((new SignatureVerifier())->verifyTon('msg', 'not-base64-of-64-bytes', str_repeat('00', 32)));
    }

    private function ethSign(string $message, string $privKey): string
    {
        $msgHash = Keccak::hash("\x19Ethereum Signed Message:\n" . strlen($message) . $message, 256, true);
        $sig     = Ecdsa::sign($msgHash, $privKey);

        return '0x' . bin2hex($sig['r'] . $sig['s'] . chr(($sig['v'] + 27) & 0xFF));
    }

    private function ethAddressFor(string $privKey): string
    {
        $point = Secp256k1::scalarMulG(
            gmp_import($privKey),
            Secp256k1::p(),
            gmp_init('0x' . Secp256k1::GX_HEX),
            gmp_init('0x' . Secp256k1::GY_HEX),
        );
        if (null === $point) {
            self::fail('point at infinity for test private key');
        }

        $x   = str_pad(gmp_strval($point[0], 16), 64, '0', \STR_PAD_LEFT);
        $y   = str_pad(gmp_strval($point[1], 16), 64, '0', \STR_PAD_LEFT);
        $pub = $this->bin($x . $y);

        return '0x' . substr(Keccak::hash($pub, 256), 24);
    }

    private function bin(string $hex): string
    {
        $bin = hex2bin($hex);
        if (false === $bin) {
            self::fail(sprintf('invalid hex fixture: %s', $hex));
        }

        return $bin;
    }
}
