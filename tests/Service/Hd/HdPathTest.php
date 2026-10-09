<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Hd;

use Amashukov\BlockchainContextBundle\Service\Hd\HdPath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HdPath::class)]
final class HdPathTest extends TestCase
{
    public function testParsesHardenedAndNormalSegmentsInEveryNotation(): void
    {
        self::assertSame(
            [44 | HdPath::HARDENED, 60 | HdPath::HARDENED, 1 | HdPath::HARDENED, 0, 7],
            HdPath::parse("m/44'/60'/1h/0/7"),
        );
        self::assertSame([2 | HdPath::HARDENED], HdPath::parse('m/2H'));
        self::assertSame([], HdPath::parse('m'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'no root' => ["44'/60'"];
        yield 'empty segment' => ["m//0"];
        yield 'letters' => ['m/abc'];
        yield 'negative' => ['m/-1'];
        yield 'leading zero' => ['m/01'];
        yield 'index past 2^31-1' => ['m/2147483648'];
    }

    #[DataProvider('invalidPaths')]
    public function testRefusesAMalformedPath(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        HdPath::parse($path);
    }

    public function testAssertIndexAcceptsTheFullNonHardenedRangeOnly(): void
    {
        HdPath::assertIndex(0);
        HdPath::assertIndex(0x7FFFFFFF);

        $this->expectException(InvalidArgumentException::class);
        HdPath::assertIndex(0x80000000);
    }

    public function testAssertIndexRefusesANegativeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HdPath::assertIndex(-1);
    }
}
