<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Numeric;

use Amashukov\BlockchainContextBundle\Service\Numeric\UsdtJettonDecimals;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsdtJettonDecimals::class)]
final class UsdtJettonDecimalsTest extends TestCase
{
    public function testDecimalsConstantIsSix(): void
    {
        self::assertSame(6, UsdtJettonDecimals::DECIMALS);
        self::assertSame('1000000', UsdtJettonDecimals::ATOMIC_UNITS_PER_USDT);
    }

    #[DataProvider('toAtomicCases')]
    public function testToAtomicScalesByTenToTheSixth(string $human, string $expectedAtomic): void
    {
        self::assertSame($expectedAtomic, UsdtJettonDecimals::toAtomic($human));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function toAtomicCases(): array
    {
        return [
            '0.90 USDT → 900_000 atomic (NOT 900_000_000 — protects against toNano regression)'  => ['0.90', '900000'],
            '1 USDT → 1_000_000 atomic'                                                          => ['1', '1000000'],
            '0.000001 USDT → 1 atomic (smallest unit)'                                           => ['0.000001', '1'],
            '0 USDT → 0 atomic'                                                                  => ['0', '0'],
            '100 USDT → 100_000_000 atomic'                                                      => ['100', '100000000'],
            'truncates below 6 decimals (no rounding)'                                           => ['0.1234567', '123456'],
        ];
    }

    #[DataProvider('fromAtomicCases')]
    public function testFromAtomicScalesByTenToTheMinusSixth(string $atomic, string $expectedHuman): void
    {
        self::assertSame($expectedHuman, UsdtJettonDecimals::fromAtomic($atomic));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fromAtomicCases(): array
    {
        return [
            '900000 atomic → 0.9 USDT'        => ['900000', '0.9'],
            '1000000 atomic → 1 USDT'         => ['1000000', '1'],
            '1 atomic → 0.000001 USDT'        => ['1', '0.000001'],
            '0 atomic → 0 USDT'               => ['0', '0'],
            '100000000 atomic → 100 USDT'     => ['100000000', '100'],
        ];
    }

    public function testToAtomicRejectsNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtJettonDecimals::toAtomic('not-a-number');
    }

    public function testToAtomicRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtJettonDecimals::toAtomic('');
    }

    public function testFromAtomicRejectsNonInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtJettonDecimals::fromAtomic('1.5');
    }

    public function testFromAtomicRejectsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UsdtJettonDecimals::fromAtomic('-1');
    }

    public function testRoundTripIsLossless(): void
    {
        foreach (['0.9', '1', '100', '0.000001', '12.345678'] as $original) {
            $atomic = UsdtJettonDecimals::toAtomic($original);
            self::assertSame($original, UsdtJettonDecimals::fromAtomic($atomic), sprintf('round-trip for %s', $original));
        }
    }
}
