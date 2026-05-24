<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\DepositTxView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(DepositTxView::class)]
final class DepositTxViewTest extends TestCase
{
    public function testExposesMinimalReadOnlyGetters(): void
    {
        $impl = new class implements DepositTxView {
            public function getBlockNumber(): string
            {
                return '12345';
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable('2026-05-17 12:00:00');
            }

            public function getConfirmationsRequired(): int
            {
                return 12;
            }

            public function getMcSeqno(): int
            {
                return 99_000;
            }
        };

        self::assertSame('12345', $impl->getBlockNumber());
        self::assertSame('2026-05-17 12:00:00', $impl->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame(12, $impl->getConfirmationsRequired());
        self::assertSame(99_000, $impl->getMcSeqno());
    }

    public function testGettersAcceptNullForOptionalFields(): void
    {
        $impl = new class implements DepositTxView {
            public function getBlockNumber(): ?string
            {
                return null;
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getConfirmationsRequired(): ?int
            {
                return null;
            }

            public function getMcSeqno(): ?int
            {
                return null;
            }
        };

        self::assertNull($impl->getBlockNumber());
        self::assertNull($impl->getConfirmationsRequired());
        self::assertNull($impl->getMcSeqno());
    }

    public function testGetterSignaturesPinTheContract(): void
    {
        $reflection = new ReflectionMethod(DepositTxView::class, 'getBlockNumber');
        self::assertSame('?string', (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(DepositTxView::class, 'getCreatedAt');
        self::assertSame(DateTimeInterface::class, (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(DepositTxView::class, 'getConfirmationsRequired');
        self::assertSame('?int', (string) $reflection->getReturnType());

        $reflection = new ReflectionMethod(DepositTxView::class, 'getMcSeqno');
        self::assertSame('?int', (string) $reflection->getReturnType());
    }
}
