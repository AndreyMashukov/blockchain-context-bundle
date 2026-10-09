<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\Ed25519HdKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ed25519HdKey::class)]
final class Ed25519HdKeyTest extends TestCase
{
    private const string SLIP10_TV1_SEED = '000102030405060708090a0b0c0d0e0f';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function slip10Ed25519VectorOne(): iterable
    {
        yield 'm' => ['m', '2b4be7f19ee27bbf30c667b642d5f4aa69fd169872f8fc3059c08ebae2eb19e7', '90046a93de5380a72b5e45010748567d5ea02bbf6522f979e05c0d8d8ca9fffb'];
        yield "m/0'" => ["m/0'", '68e0fe46dfb67e368c75379acec591dad19df3cde26e63b93a8e704f1dade7a3', '8b59aa11380b624e81507a27fedda59fea6d0b779a778918a2fd3590e16e9c69'];
        yield "m/0'/1'" => ["m/0'/1'", 'b1d0bad404bf35da785a64ca1ac54b2617211d2777696fbffaf208f746ae84f2', 'a320425f77d1b5c2505a6b1b27382b37368ee640e3557c315416801243552f14'];
        yield "m/0'/1'/2'" => ["m/0'/1'/2'", '92a5b23c0b8a99e37d07df3fb9966917f5d06e02ddbd909c7e184371463e9fc9', '2e69929e00b5ab250f49c3fb1c12f252de4fed2c1db88387094a0f8c4c9ccd6c'];
    }

    #[DataProvider('slip10Ed25519VectorOne')]
    public function testMatchesTheOfficialSlip10Ed25519VectorOne(string $path, string $privateKey, string $chainCode): void
    {
        $key = Ed25519HdKey::fromSeed((string) hex2bin(self::SLIP10_TV1_SEED))->derivePath($path);

        self::assertSame($privateKey, bin2hex($key->privateKey));
        self::assertSame($chainCode, bin2hex($key->chainCode));
    }

    public function testMasterPublicKeyMatchesTheOfficialVector(): void
    {
        $master = Ed25519HdKey::fromSeed((string) hex2bin(self::SLIP10_TV1_SEED));

        self::assertSame('a4b2856bfec510abab89753fac1ac0e1112364e7d250545963f135f2a33188ed', bin2hex($master->keyPair()->publicKey));
    }

    public function testRefusesANonHardenedChild(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ed25519HdKey::fromSeed((string) hex2bin(self::SLIP10_TV1_SEED))->derivePath("m/0'/1");
    }
}
