<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderChain;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxBuilderInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxPayload;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxStep;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\UserWalletInterface;
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

            public function build(DepositTxOrderView $order): DepositTxPayload
            {
                $this->built = true;

                return new DepositTxPayload('ton-native', ['marker' => 'matched']);
            }

            public function nextStep(DepositTxOrderView $order): DepositTxStep
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

            public function build(DepositTxOrderView $order): DepositTxPayload
            {
                return new DepositTxPayload('ton-native', ['marker' => 'tail']);
            }

            public function nextStep(DepositTxOrderView $order): DepositTxStep
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

                public function build(DepositTxOrderView $order): DepositTxPayload
                {
                    return new DepositTxPayload('evm-native', []);
                }

                public function nextStep(DepositTxOrderView $order): DepositTxStep
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

    public function testOrderIsForwardedToMatchingBuilder(): void
    {
        $matched = new class implements DepositTxBuilderInterface {
            public ?string $seenUserAddress = null;

            public ?string $seenJettonWallet = null;

            public function supports(string $chain): bool
            {
                return 'usdt_jetton' === $chain;
            }

            public function build(DepositTxOrderView $order): DepositTxPayload
            {
                $this->seenUserAddress  = $order->getUserWallet()?->userAddress();
                $this->seenJettonWallet = $order->getUserWallet()?->userJettonWallet();

                return new DepositTxPayload('ton-jetton', []);
            }

            public function nextStep(DepositTxOrderView $order): DepositTxStep
            {
                return DepositTxStep::done();
            }
        };

        $chain = new DepositTxBuilderChain([$matched]);
        $chain->build($this->orderView('usdt_jetton'));

        self::assertSame('UQuser_address', $matched->seenUserAddress);
        self::assertSame('EQuser_jetton_wallet', $matched->seenJettonWallet);
    }

    private function orderView(string $chain): DepositTxOrderView
    {
        $wallet = new readonly class implements UserWalletInterface {
            public function userAddress(): string
            {
                return 'UQuser_address';
            }

            public function userJettonWallet(): string
            {
                return 'EQuser_jetton_wallet';
            }
        };

        return new readonly class ($chain, $wallet) implements DepositTxOrderView {
            public function __construct(private string $chain, private UserWalletInterface $wallet) {}

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

            public function getUserWallet(): UserWalletInterface
            {
                return $this->wallet;
            }
        };
    }
}
