<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\HdPath;
use Amashukov\BlockchainContextBundle\Service\Hd\Secp256k1HdKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Secp256k1HdKey::class)]
final class Secp256k1HdKeyTest extends TestCase
{
    private const string BIP32_TV1_SEED = '000102030405060708090a0b0c0d0e0f';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function bip32TestVectorOne(): iterable
    {
        yield 'm' => ['m', 'e8f32e723decf4051aefac8e2c93c9c5b214313817cdb01a1494b917c8436b35', '873dff81c02f525623fd1fe5167eac3a55a049de3d314bb42ee227ffed37d508'];
        yield "m/0'" => ["m/0'", 'edb2e14f9ee77d26dd93b4ecede8d16ed408ce149b6cd80b0715a2d911a0afea', '47fdacbd0f1097043b78c63c20c34ef4ed9a111d980047ad16282c7ae6236141'];
        yield "m/0'/1" => ["m/0'/1", '3c6cb8d0f6a264c91ea8b5030fadaa8e538b020f0a387421a12de9319dc93368', '2a7857631386ba23dacac34180dd1983734e444fdbf774041578e9b6adb37c19'];
        yield "m/0'/1/2'" => ["m/0'/1/2'", 'cbce0d719ecf7431d88e6a89fa1483e02e35092af60c042b1df2ff59fa424dca', '04466b9cc8e161e966409ca52986c584f07e9dc81f735db683c3ff6ec7b1503f'];
        yield "m/0'/1/2'/2" => ["m/0'/1/2'/2", '0f479245fb19a38a1954c5c7c0ebab2f9bdfd96a17563ef28a6a4b1a2a764ef4', 'cfb71883f01676f587d023cc53a35bc7f88f724b1f8c2892ac1275ac822a3edd'];
        yield "m/0'/1/2'/2/1000000000" => ["m/0'/1/2'/2/1000000000", '471b76e389e528d6de6d816857e012c5455051cad6660850e58372a6c3e6e7c8', 'c783e67b921d2beb8f6b389cc646d7263b4145701dadd2161548a8b078e65e9e'];
    }

    #[DataProvider('bip32TestVectorOne')]
    public function testMatchesTheOfficialBip32VectorOne(string $path, string $privateKey, string $chainCode): void
    {
        $key = Secp256k1HdKey::fromSeed((string) hex2bin(self::BIP32_TV1_SEED))->derivePath($path);

        self::assertSame($privateKey, bin2hex($key->privateKey));
        self::assertSame($chainCode, bin2hex($key->chainCode));
    }

    public function testMasterPublicKeyMatchesTheOfficialVector(): void
    {
        $master = Secp256k1HdKey::fromSeed((string) hex2bin(self::BIP32_TV1_SEED));

        self::assertSame('0339a36013301597daef41fbe593a02cc513d0b55527ec2df1050e2e8ff49c85c2', bin2hex($master->compressedPublicKey()));
        self::assertSame(substr(bin2hex($master->compressedPublicKey()), 2), substr(bin2hex($master->uncompressedPublicKey()), 0, 64));
        self::assertSame(64, \strlen($master->uncompressedPublicKey()));
    }

    public function testDeriveStepByStepEqualsDerivePath(): void
    {
        $master = Secp256k1HdKey::fromSeed((string) hex2bin(self::BIP32_TV1_SEED));

        self::assertSame(
            bin2hex($master->derivePath("m/0'/1")->privateKey),
            bin2hex($master->derive(0 | HdPath::HARDENED)->derive(1)->privateKey),
        );
    }

    public function testRefusesAChildIndexBeyondThirtyTwoBits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Secp256k1HdKey::fromSeed((string) hex2bin(self::BIP32_TV1_SEED))->derive(0x100000000);
    }
}
