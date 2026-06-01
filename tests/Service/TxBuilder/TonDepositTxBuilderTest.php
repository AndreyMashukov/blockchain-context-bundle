<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositEncoderInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\SignerInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\TonDepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\VaultInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TonDepositTxBuilder::class)]
final class TonDepositTxBuilderTest extends TestCase
{
    private const array TX = ['address' => '0:vault', 'amount' => '1500000000', 'payload' => 'base64body'];

    public function testSupportsTonOnly(): void
    {
        $builder = new TonDepositTxBuilder($this->encoder());
        self::assertTrue($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
    }

    public function testBuildDelegatesTonNativeEncodingTargetingVault(): void
    {
        $encoder = $this->createMock(DepositEncoderInterface::class);
        $encoder->expects(self::once())
            ->method('tonNativeDeposit')
            ->with(self::isInstanceOf(DepositTxOrderView::class), '0:vault')
            ->willReturn(self::TX);

        $payload = (new TonDepositTxBuilder($encoder))->build($this->order());

        self::assertSame('ton-native', $payload->kind);
        self::assertSame(self::TX, $payload->payload);
    }

    public function testNextStepWrapsEncoderTxAsDepositNativeStep(): void
    {
        $step = (new TonDepositTxBuilder($this->encoder()))->nextStep($this->order(), $this->signer());

        self::assertSame('ton-deposit-native', $step->kind);
        self::assertSame(self::TX, $step->tx);
    }

    private function encoder(): DepositEncoderInterface
    {
        $encoder = $this->createStub(DepositEncoderInterface::class);
        $encoder->method('tonNativeDeposit')->willReturn(self::TX);

        return $encoder;
    }

    private function signer(): SignerInterface
    {
        return new readonly class implements SignerInterface {
            public function getAddress(): string
            {
                return '0:signer';
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
                return 'ton';
            }

            public function getDepositAddress(): ?string
            {
                return null;
            }

            public function getFromAmount(): string
            {
                return '1.5';
            }

            public function getDepositMemo(): string
            {
                return 'memo42';
            }

            public function getVault(): VaultInterface
            {
                return $this->vault;
            }
        };
    }
}
