<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Detection;

use Amashukov\BlockchainContextBundle\Service\Detection\ChainDepositCheckChain;
use Amashukov\BlockchainContextBundle\Service\Detection\ChainDepositCheckInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ChainDepositCheckChain::class)]
final class ChainDepositCheckChainTest extends TestCase
{
    public function testFirstMatchingCheckRuns(): void
    {
        $matched = new class implements ChainDepositCheckInterface {
            public function supports(OrderView $order): bool
            {
                return true;
            }

            public function check(OrderView $order): bool
            {
                return true;
            }
        };
        $tail = new class implements ChainDepositCheckInterface {
            public bool $consulted = false;

            public function supports(OrderView $order): bool
            {
                $this->consulted = true;

                return true;
            }

            public function check(OrderView $order): bool
            {
                return false;
            }
        };

        $chain = new ChainDepositCheckChain([$matched, $tail], new NullLogger());
        self::assertTrue($chain->check($this->orderView()));
        self::assertFalse($tail->consulted);
    }

    public function testNoMatchReturnsFalseAndLogs(): void
    {
        $check = new class implements ChainDepositCheckInterface {
            public function supports(OrderView $order): bool
            {
                return false;
            }

            public function check(OrderView $order): bool
            {
                return true;
            }
        };
        $chain = new ChainDepositCheckChain([$check], new NullLogger());
        self::assertFalse($chain->check($this->orderView()));
    }

    public function testEmptyChainReturnsFalse(): void
    {
        $chain = new ChainDepositCheckChain([], new NullLogger());
        self::assertFalse($chain->check($this->orderView()));
    }

    private function orderView(): OrderView
    {
        return new class implements OrderView {
            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getFromChain(): string
            {
                return 'eth';
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
