<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\Bip39Seed;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bip39Seed::class)]
final class Bip39SeedTest extends TestCase
{
    private const string PHRASE = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

    public function testMatchesTheOfficialBip39VectorWithAPassphrase(): void
    {
        self::assertSame(
            'c55257c360c07c72029aebc1b53c05ed0362ada38ead3e3e9efa3708e53495531f09a6987599d18264c1e1c92f2cf141630c7a3c4ab7c81b2f001698e7463b04',
            bin2hex(Bip39Seed::fromMnemonic(self::PHRASE, 'TREZOR')),
        );
    }

    public function testCollapsesWhitespaceBetweenWords(): void
    {
        self::assertSame(
            Bip39Seed::fromMnemonic(self::PHRASE),
            Bip39Seed::fromMnemonic("  " . str_replace(' ', "  \n", self::PHRASE) . "\t"),
        );
    }

    public function testRefusesAPhraseOfTheWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('got 11');

        Bip39Seed::fromMnemonic(implode(' ', \array_slice(explode(' ', self::PHRASE), 1)));
    }

    public function testRefusesAnEmptyPhraseWithoutEchoingIt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('got 0');

        Bip39Seed::fromMnemonic('   ');
    }
}
