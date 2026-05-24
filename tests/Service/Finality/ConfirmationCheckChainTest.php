<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCheckChain;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCheckInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationState;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ConfirmationCheckChain::class)]
final class ConfirmationCheckChainTest extends TestCase
{
    public function testFirstMatchingCheckRuns(): void
    {
        $matched = new class implements ConfirmationCheckInterface {
            public function supports(OrderView $order): bool
            {
                return true;
            }

            public function check(OrderView $order): ConfirmationState
            {
                return ConfirmationState::CONFIRMED;
            }
        };
        $tail = new class implements ConfirmationCheckInterface {
            public bool $consulted = false;

            public function supports(OrderView $order): bool
            {
                $this->consulted = true;

                return true;
            }

            public function check(OrderView $order): ConfirmationState
            {
                return ConfirmationState::REVERTED;
            }
        };

        $chain = new ConfirmationCheckChain([$matched, $tail], new NullLogger());
        self::assertSame(ConfirmationState::CONFIRMED, $chain->check($this->orderView()));
        self::assertFalse($tail->consulted, 'tail must not be consulted after first match');
    }

    public function testRevertedPassesThroughUnchanged(): void
    {
        $check = new class implements ConfirmationCheckInterface {
            public function supports(OrderView $order): bool
            {
                return true;
            }

            public function check(OrderView $order): ConfirmationState
            {
                return ConfirmationState::REVERTED;
            }
        };
        $chain = new ConfirmationCheckChain([$check], new NullLogger());
        self::assertSame(ConfirmationState::REVERTED, $chain->check($this->orderView()));
    }

    public function testNoMatchReturnsDefensivePending(): void
    {
        $check = new class implements ConfirmationCheckInterface {
            public function supports(OrderView $order): bool
            {
                return false;
            }

            public function check(OrderView $order): ConfirmationState
            {
                return ConfirmationState::CONFIRMED;
            }
        };
        $chain = new ConfirmationCheckChain([$check], new NullLogger());
        self::assertSame(ConfirmationState::PENDING, $chain->check($this->orderView()));
    }

    public function testEmptyChainReturnsPending(): void
    {
        $chain = new ConfirmationCheckChain([], new NullLogger());
        self::assertSame(ConfirmationState::PENDING, $chain->check($this->orderView()));
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
                return 'ton';
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
