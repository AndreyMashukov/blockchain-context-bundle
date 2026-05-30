<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderChain;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxPayload;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxStep;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DepositTxBuilderChain::class)]
final class DepositTxBuilderChainTest extends TestCase
{
    public function testFirstMatchingBuilderRuns(): void
    {
        $matched = new class implements DepositTxBuilderInterface {
            public bool $built = false;

            public function supports(string $chain): bool
            {
                return 'ton' === $chain;
            }

            public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
            {
                $this->built = true;

                return new DepositTxPayload('ton-native', ['marker' => 'matched']);
            }

            public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
            {
                return DepositTxStep::done();
            }
        };
        $tail = new class implements DepositTxBuilderInterface {
            public bool $consulted = false;

            public function supports(string $chain): bool
            {
                $this->consulted = true;

                return true;
            }

            public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
            {
                return new DepositTxPayload('ton-native', ['marker' => 'tail']);
            }

            public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
            {
                return DepositTxStep::done();
            }
        };

        $chain   = new DepositTxBuilderChain([$matched, $tail]);
        $payload = $chain->build($this->orderView('ton'));

        self::assertSame('matched', $payload->payload['marker']);
        self::assertTrue($matched->built);
        self::assertFalse($tail->consulted);
    }

    public function testUnknownChainThrowsLogicException(): void
    {
        $chain = new DepositTxBuilderChain([
            new class implements DepositTxBuilderInterface {
                public function supports(string $chain): bool
                {
                    return 'eth' === $chain;
                }

                public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
                {
                    return new DepositTxPayload('evm-native', []);
                }

                public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
                {
                    return DepositTxStep::done();
                }
            },
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No DepositTxBuilder supports chain "ton"');

        $chain->build($this->orderView('ton'));
    }

    public function testEmptyChainThrowsLogicException(): void
    {
        $chain = new DepositTxBuilderChain([]);

        $this->expectException(LogicException::class);

        $chain->build($this->orderView('ton'));
    }

    public function testContextIsForwardedToMatchingBuilder(): void
    {
        $matched = new class implements DepositTxBuilderInterface {
            /**
             * @param array<string, mixed> $seenContext
             */
            public function __construct(public array $seenContext = []) {}

            public function supports(string $chain): bool
            {
                return 'usdt_jetton' === $chain;
            }

            public function build(DepositTxOrderView $order, array $context = []): DepositTxPayload
            {
                $this->seenContext = $context;

                return new DepositTxPayload('ton-jetton', []);
            }

            public function nextStep(DepositTxOrderView $order, array $context = []): DepositTxStep
            {
                return DepositTxStep::done();
            }
        };

        $chain = new DepositTxBuilderChain([$matched]);
        $chain->build($this->orderView('usdt_jetton'), [
            'userAddress'      => 'UQuser_address',
            'userJettonWallet' => 'EQuser_jetton_wallet',
        ]);

        self::assertSame('UQuser_address', $matched->seenContext['userAddress']);
        self::assertSame('EQuser_jetton_wallet', $matched->seenContext['userJettonWallet']);
    }

    private function orderView(string $chain): DepositTxOrderView
    {
        return new readonly class ($chain) implements DepositTxOrderView {
            public function __construct(private string $chain) {}

            public function getId(): int
            {
                return 42;
            }

            public function getOrderId(): string
            {
                return '11111111-1111-1111-1111-111111111111';
            }

            public function getFromChain(): string
            {
                return $this->chain;
            }

            public function getDepositAddress(): string
            {
                return '0x000000000000000000000000000000000000beef';
            }

            public function getFromAmount(): string
            {
                return '1.0';
            }

            public function getDepositMemo(): string
            {
                return 'memo42';
            }
        };
    }
}
