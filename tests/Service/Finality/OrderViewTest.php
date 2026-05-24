<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(OrderView::class)]
final class OrderViewTest extends TestCase
{
    public function testExposesMinimalReadOnlyGetters(): void
    {
        $impl = new class implements OrderView {
            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable('2026-05-01 09:30:00');
            }

            public function getFromChain(): string
            {
                return 'eth';
            }

            public function getOrderId(): string
            {
                return 'abcd-1234';
            }

            public function getIncomingTxHash(): string
            {
                return '0xdeadbeef';
            }
        };

        self::assertSame('2026-05-01 09:30:00', $impl->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame('eth', $impl->getFromChain());
        self::assertSame('abcd-1234', $impl->getOrderId());
        self::assertSame('0xdeadbeef', $impl->getIncomingTxHash());
    }

    public function testNullableGettersAcceptNull(): void
    {
        $impl = new class implements OrderView {
            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getFromChain(): ?string
            {
                return null;
            }

            public function getOrderId(): ?string
            {
                return null;
            }

            public function getIncomingTxHash(): ?string
            {
                return null;
            }
        };

        self::assertNull($impl->getFromChain());
        self::assertNull($impl->getOrderId());
        self::assertNull($impl->getIncomingTxHash());
    }

    public function testGetterSignaturesPinTheContract(): void
    {
        $reflection = new ReflectionMethod(OrderView::class, 'getCreatedAt');
        self::assertSame(DateTimeInterface::class, (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(OrderView::class, 'getFromChain');
        self::assertSame('?string', (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(OrderView::class, 'getOrderId');
        self::assertSame('?string', (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(OrderView::class, 'getIncomingTxHash');
        self::assertSame('?string', (string) $reflection->getReturnType());
    }
}
