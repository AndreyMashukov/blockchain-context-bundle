<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\Bip39Mnemonic;
use Amashukov\BlockchainContextBundle\Service\Hd\Bip39Seed;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bip39Mnemonic::class)]
final class Bip39MnemonicTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function officialVectors(): iterable
    {
        yield '128 bits of zeros' => ['00000000000000000000000000000000', 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about'];
        yield '128 bits of 0x7f' => ['7f7f7f7f7f7f7f7f7f7f7f7f7f7f7f7f', 'legal winner thank year wave sausage worth useful legal winner thank yellow'];
        yield '256 bits of zeros' => [str_repeat('00', 32), 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art'];
        yield '256 bits of 0x7f' => [str_repeat('7f', 32), 'legal winner thank year wave sausage worth useful legal winner thank year wave sausage worth useful legal winner thank year wave sausage worth title'];
        yield '256 bits of 0xff' => [str_repeat('ff', 32), 'zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo vote'];
        yield '256-bit random vector' => ['68a79eaca2324873eacc50cb9c6eca8cc68ea5d936f98787c60c7ebc74e6ce7c', 'hamster diagram private dutch cause delay private meat slide toddler razor book happy fancy gospel tennis maple dilemma loan word shrug inflict delay length'];
    }

    #[DataProvider('officialVectors')]
    public function testMatchesTheOfficialBip39Vectors(string $entropyHex, string $mnemonic): void
    {
        self::assertSame($mnemonic, Bip39Mnemonic::fromEntropy((string) hex2bin($entropyHex)));
        self::assertTrue(Bip39Mnemonic::isValid($mnemonic));
    }

    public function testGeneratesAValidTwentyFourWordMnemonicByDefault(): void
    {
        $mnemonic = Bip39Mnemonic::generate();

        self::assertCount(24, explode(' ', $mnemonic));
        self::assertTrue(Bip39Mnemonic::isValid($mnemonic));
        self::assertSame(64, \strlen(Bip39Seed::fromMnemonic($mnemonic)));
    }

    public function testTwoGeneratedMnemonicsDiffer(): void
    {
        self::assertNotSame(Bip39Mnemonic::generate(), Bip39Mnemonic::generate());
    }

    public function testGeneratesTwelveWordsOnRequest(): void
    {
        self::assertCount(12, explode(' ', Bip39Mnemonic::generate(12)));
    }

    public function testRejectsAWordCountOutsideTheStandard(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Bip39Mnemonic::generate(13);
    }

    public function testRejectsEntropyOfAnUnsupportedLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Bip39Mnemonic::fromEntropy(str_repeat("\0", 15));
    }

    public function testABrokenChecksumIsNotValid(): void
    {
        self::assertFalse(Bip39Mnemonic::isValid('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon'));
    }

    public function testAWordOutsideTheListIsNotValid(): void
    {
        self::assertFalse(Bip39Mnemonic::isValid('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon notaword'));
    }
}
