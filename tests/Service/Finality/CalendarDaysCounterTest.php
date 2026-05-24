<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\CalendarDaysCounter;
use Amashukov\BlockchainContextBundle\Service\Finality\DepositTxView;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(CalendarDaysCounter::class)]
final class CalendarDaysCounterTest extends TestCase
{
    public function testSupportsStarsChainOnly(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2026-05-17 12:00:00'));
        $counter = new CalendarDaysCounter($clock);

        self::assertTrue($counter->supports('stars'));
        self::assertFalse($counter->supports('ton'));
        self::assertFalse($counter->supports('eth'));
    }

    public function testCountReturnsFlooredDaysSinceOrderCreated(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2026-05-10 00:00:00'));
        $counter = new CalendarDaysCounter($clock);

        $tx    = $this->tx(required: 21);
        $order = $this->order(createdAt: new DateTimeImmutable('2026-05-03 00:00:00'));

        self::assertSame(7, $counter->count($tx, $order));
    }

    public function testCountCapsAtRequired(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2027-05-10 00:00:00'));
        $counter = new CalendarDaysCounter($clock);

        $tx    = $this->tx(required: 21);
        $order = $this->order(createdAt: new DateTimeImmutable('2026-05-03 00:00:00'));

        self::assertSame(21, $counter->count($tx, $order));
    }

    public function testCountReturnsZeroWhenRequiredZero(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2026-05-10 00:00:00'));
        $counter = new CalendarDaysCounter($clock);

        $tx    = $this->tx(required: 0);
        $order = $this->order(createdAt: new DateTimeImmutable('2026-05-03 00:00:00'));

        self::assertSame(0, $counter->count($tx, $order));
    }

    public function testCountReturnsZeroBeforeOrderCreatedAt(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2026-05-01 00:00:00'));
        $counter = new CalendarDaysCounter($clock);

        $tx    = $this->tx(required: 21);
        $order = $this->order(createdAt: new DateTimeImmutable('2026-05-03 00:00:00'));

        self::assertSame(0, $counter->count($tx, $order));
    }

    private function tx(int $required): DepositTxView
    {
        return new readonly class ($required) implements DepositTxView {
            public function __construct(private int $required) {}

            public function getBlockNumber(): ?string
            {
                return null;
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getConfirmationsRequired(): int
            {
                return $this->required;
            }

            public function getMcSeqno(): ?int
            {
                return null;
            }
        };
    }

    private function order(DateTimeImmutable $createdAt): OrderView
    {
        return new readonly class ($createdAt) implements OrderView {
            public function __construct(private DateTimeImmutable $createdAt) {}

            public function getCreatedAt(): DateTimeInterface
            {
                return $this->createdAt;
            }

            public function getFromChain(): string
            {
                return 'stars';
            }

            public function getOrderId(): string
            {
                return '00000000-0000-0000-0000-000000000000';
            }

            public function getIncomingTxHash(): ?string
            {
                return null;
            }
        };
    }
}
