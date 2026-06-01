<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositEncoderInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\EthDepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\SignerInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\VaultInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EthDepositTxBuilder::class)]
final class EthDepositTxBuilderTest extends TestCase
{
    private const array TX = ['to' => '0xvault', 'data' => '0xdeadbeef', 'value' => '0x1', 'chainId' => '0x1'];

    public function testSupportsEthOnly(): void
    {
        $builder = new EthDepositTxBuilder($this->encoder());
        self::assertTrue($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
    }

    public function testBuildDelegatesEvmNativeEncodingTargetingVault(): void
    {
        $encoder = $this->createMock(DepositEncoderInterface::class);
        $encoder->expects(self::once())
            ->method('evmNativeDeposit')
            ->with(self::isInstanceOf(DepositTxOrderView::class), '0:vault')
            ->willReturn(self::TX);

        $payload = (new EthDepositTxBuilder($encoder))->build($this->order());

        self::assertSame('evm-native', $payload->kind);
        self::assertSame(self::TX, $payload->payload);
    }

    public function testNextStepWrapsEncoderTxAsDepositNativeStep(): void
    {
        $step = (new EthDepositTxBuilder($this->encoder()))->nextStep($this->order(), $this->signer());

        self::assertSame('evm-deposit-native', $step->kind);
        self::assertSame(self::TX, $step->tx);
        self::assertFalse($step->done);
    }

    private function encoder(): DepositEncoderInterface
    {
        $encoder = $this->createStub(DepositEncoderInterface::class);
        $encoder->method('evmNativeDeposit')->willReturn(self::TX);

        return $encoder;
    }

    private function signer(): SignerInterface
    {
        return new readonly class implements SignerInterface {
            public function getAddress(): string
            {
                return '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
            }
        };
    }

    private function order(): DepositTxOrderView
    {
        $vault = new readonly class implements VaultInterface {
            public function getAddress(): string
            {
                return '0:vault';
            }
        };

        return new readonly class ($vault) implements DepositTxOrderView {
            public function __construct(private VaultInterface $vault) {}

            public function getId(): int
            {
                return 1;
            }

            public function getOrderId(): string
            {
                return '00000000-0000-0000-0000-000000000001';
            }

            public function getFromChain(): string
            {
                return 'eth';
            }

            public function getDepositAddress(): ?string
            {
                return null;
            }

            public function getFromAmount(): string
            {
                return '1.5';
            }

            public function getDepositMemo(): ?string
            {
                return null;
            }

            public function getVault(): VaultInterface
            {
                return $this->vault;
            }
        };
    }
}
