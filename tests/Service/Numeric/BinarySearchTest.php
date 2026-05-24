<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Numeric;

use Amashukov\BlockchainContextBundle\Service\Numeric\BinarySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BinarySearch::class)]
final class BinarySearchTest extends TestCase
{
    private BinarySearch $service;

    protected function setUp(): void
    {
        $this->service = new BinarySearch();
    }

    public function testSearchReturnsHighestPassingValue(): void
    {
        $result = $this->service->search(0, 100, 1, static fn(int $v): bool => $v <= 42);
        self::assertSame(42, $result);
    }

    public function testSearchReturnsZeroWhenNothingPasses(): void
    {
        $result = $this->service->search(1, 1000, 1, static fn(int $v): bool => false);
        self::assertSame(0, $result);
    }

    public function testSearchReturnsTopWhenAllPass(): void
    {
        $result = $this->service->search(1, 1000, 1, static fn(int $v): bool => true);
        self::assertSame(1000, $result);
    }

    public function testSearchHandlesSinglePointRangeWhenPredicatePasses(): void
    {
        $result = $this->service->search(7, 7, 1, static fn(int $v): bool => 7 === $v);
        self::assertSame(7, $result);
    }

    public function testSearchHandlesSinglePointRangeWhenPredicateFails(): void
    {
        $result = $this->service->search(7, 7, 1, static fn(int $v): bool => false);
        self::assertSame(0, $result);
    }

    public function testSearchInvertedRangeReturnsZero(): void
    {
        $result = $this->service->search(100, 1, 1, static fn(int $v): bool => true);
        self::assertSame(0, $result);
    }

    public function testSearchHonoursStepGreaterThanOne(): void
    {
        $calls  = [];
        $result = $this->service->search(0, 100, 5, static function (int $v) use (&$calls): bool {
            $calls[] = $v;

            return $v <= 50;
        });

        self::assertSame(50, $result);
        self::assertGreaterThanOrEqual(1, \count($calls));
        self::assertLessThanOrEqual(10, \count($calls));
        self::assertContains(50, $calls);
    }

    public function testSearchInvokesPredicateLogarithmicallyForLargeRange(): void
    {
        $calls = 0;
        $this->service->search(1, 1_000_000, 1, static function () use (&$calls): bool {
            ++$calls;

            return true;
        });

        self::assertLessThan(40, $calls);
    }
}
