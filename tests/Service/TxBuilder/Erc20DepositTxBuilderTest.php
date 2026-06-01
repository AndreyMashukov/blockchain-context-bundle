<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositEncoderInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\Erc20DepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\SignerInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\VaultInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(Erc20DepositTxBuilder::class)]
final class Erc20DepositTxBuilderTest extends TestCase
{
    private const array APPROVE = ['to' => '0xtoken', 'data' => '0xapprove', 'value' => '0x0', 'chainId' => '0x1'];

    private const array DEPOSIT = ['to' => '0xvault', 'data' => '0xdeposit', 'value' => '0x0', 'chainId' => '0x1'];

    public function testSupportsUsdtErc20Only(): void
    {
        $builder = new Erc20DepositTxBuilder($this->encoder(true, true));
        self::assertTrue($builder->supports('usdt_erc20'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
    }

    public function testBuildDelegatesApprovePlusDepositEncoding(): void
    {
        $payload = (new Erc20DepositTxBuilder($this->encoder(true, true)))->build($this->order());

        self::assertSame('evm-erc20', $payload->kind);
        self::assertSame(self::APPROVE, $payload->payload['approve']);
        self::assertSame(self::DEPOSIT, $payload->payload['deposit']);
    }

    public function testNextStepReturnsDepositWhenAllowanceSatisfied(): void
    {
        $step = (new Erc20DepositTxBuilder($this->encoder(balance: true, allowance: true)))->nextStep($this->order(), $this->signer());

        self::assertSame('evm-deposit-erc20', $step->kind);
        self::assertSame(self::DEPOSIT, $step->tx);
    }

    public function testNextStepReturnsApproveWhenAllowanceMissing(): void
    {
        $step = (new Erc20DepositTxBuilder($this->encoder(balance: true, allowance: false)))->nextStep($this->order(), $this->signer());

        self::assertSame('evm-approve', $step->kind);
        self::assertSame(self::APPROVE, $step->tx);
    }

    public function testNextStepThrowsWhenSignerBalanceInsufficient(): void
    {
        $builder = new Erc20DepositTxBuilder($this->encoder(balance: false, allowance: true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signer USDT balance');

        $builder->nextStep($this->order(), $this->signer());
    }

    private function encoder(bool $balance, bool $allowance): DepositEncoderInterface&Stub
    {
        $encoder = $this->createStub(DepositEncoderInterface::class);
        $encoder->method('erc20Approve')->willReturn(self::APPROVE);
        $encoder->method('erc20Deposit')->willReturn(self::DEPOSIT);
        $encoder->method('signerHasSufficientBalance')->willReturn($balance);
        $encoder->method('allowanceSatisfied')->willReturn($allowance);

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
                return '0xvault';
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
                return 'usdt_erc20';
            }

            public function getDepositAddress(): ?string
            {
                return null;
            }

            public function getFromAmount(): string
            {
                return '100';
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
