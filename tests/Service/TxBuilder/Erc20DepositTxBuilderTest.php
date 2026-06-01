<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\Erc20DepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\GasEstimatorInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\NullGasEstimator;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\UserWalletInterface;
use Amashukov\EthRpc\EthRpcClientInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Erc20DepositTxBuilder::class)]
final class Erc20DepositTxBuilderTest extends TestCase
{
    private const string USDT = '0xdAC17F958D2ee523a2206206994597C13D831ec7';

    private const string UUID = 'f8a3b2c1-4d5e-6789-abcd-ef0123456789';

    private const string UUID_HEX = 'f8a3b2c14d5e6789abcdef0123456789';

    private const string USER = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function newBuilder(
        int $chainId,
        ?EthRpcClientInterface $ethRpc = null,
        ?GasEstimatorInterface $gasEstimator = null,
    ): Erc20DepositTxBuilder {
        return new Erc20DepositTxBuilder(
            usdtTokenAddress: self::USDT,
            chainId: $chainId,
            uuidIntCodec: new UuidIntCodec(),
            ethRpc: $ethRpc ?? $this->ethRpcReturning('0x0'),
            gasEstimator: $gasEstimator ?? new NullGasEstimator(),
        );
    }

    private function ethRpcReturning(string $hex): EthRpcClientInterface
    {
        $rpc = $this->createStub(EthRpcClientInterface::class);
        $rpc->method('eth_call')->willReturn($hex);

        return $rpc;
    }

    public function testSupportsUsdtErc20Only(): void
    {
        $builder = $this->newBuilder(1);
        self::assertTrue($builder->supports('usdt_erc20'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
    }

    public function testBuildEmitsApprovePlusDepositPair(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '100'));

        self::assertSame('evm-erc20', $payload->kind);
        self::assertArrayHasKey('approve', $payload->payload);
        self::assertArrayHasKey('deposit', $payload->payload);

        self::assertSame(strtolower(self::USDT), $this->field($payload->payload, 'approve', 'to'));
        self::assertSame('0x1234567890abcdef1234567890abcdef12345678', $this->field($payload->payload, 'deposit', 'to'));

        self::assertSame('0x0', $this->field($payload->payload, 'approve', 'value'));
        self::assertSame('0x0', $this->field($payload->payload, 'deposit', 'value'));
        self::assertSame('0x1', $this->field($payload->payload, 'approve', 'chainId'));
        self::assertSame('0x1', $this->field($payload->payload, 'deposit', 'chainId'));
    }

    public function testApproveCalldataMatchesErc20Selector(): void
    {
        $expectedSelector = '0x' . AbiEncoder::methodId('approve(address,uint256)');

        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '100'));

        self::assertStringStartsWith($expectedSelector, $this->field($payload->payload, 'approve', 'data'));
    }

    public function testDepositCalldataMatchesBridgeSelector(): void
    {
        $expectedSelector = '0x' . AbiEncoder::methodId('depositTokenForBridge(address,uint256,uint256)');

        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '100'));

        self::assertStringStartsWith($expectedSelector, $this->field($payload->payload, 'deposit', 'data'));
    }

    public function testAmountConvertsToSixDecimalUnits(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '1.5'));

        self::assertSame(
            str_pad('16e360', 64, '0', \STR_PAD_LEFT),
            substr($this->field($payload->payload, 'approve', 'data'), -64),
        );
    }

    public function testDepositCalldataEncodesUuidAsLastUint256(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '1.5'));

        self::assertStringEndsWith(str_pad(self::UUID_HEX, 64, '0', \STR_PAD_LEFT), $this->field($payload->payload, 'deposit', 'data'));
    }

    public function testBuildLowercasesTokenAndBridgeAddresses(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '100'));

        self::assertSame(strtolower(self::USDT), $this->field($payload->payload, 'approve', 'to'));
        self::assertSame('0x1234567890abcdef1234567890abcdef12345678', $this->field($payload->payload, 'deposit', 'to'));
    }

    public function testBuildHonoursChainIdEnvOverride(): void
    {
        $builder = $this->newBuilder(11155111);
        $payload = $builder->build($this->order(self::UUID, '1'));

        self::assertSame('0xaa36a7', $this->field($payload->payload, 'approve', 'chainId'));
        self::assertSame('0xaa36a7', $this->field($payload->payload, 'deposit', 'chainId'));
    }

    public function testNextStepRejectsOrderWithoutBoundWallet(): void
    {
        $builder = $this->newBuilder(1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a bound user wallet');

        $builder->nextStep($this->order(self::UUID, '100', userAddress: null));
    }

    public function testNextStepReturnsApproveWhenAllowanceInsufficient(): void
    {
        $builder = $this->newBuilder(1, $this->ethRpcReturning('0x0'));
        $step    = $builder->nextStep($this->order(self::UUID, '100'));

        self::assertSame('evm-approve', $step->kind);
        self::assertSame(strtolower(self::USDT), $this->txField($step->tx, 'to'));
    }

    public function testNextStepReturnsDepositWhenAllowanceSufficient(): void
    {
        $builder = $this->newBuilder(1, $this->ethRpcReturning('0x' . str_repeat('f', 64)));
        $step    = $builder->nextStep($this->order(self::UUID, '100'));

        self::assertSame('evm-deposit-erc20', $step->kind);
        self::assertSame('0x1234567890abcdef1234567890abcdef12345678', $this->txField($step->tx, 'to'));
    }

    public function testNextStepStampsGasFromEstimator(): void
    {
        $gas = $this->createStub(GasEstimatorInterface::class);
        $gas->method('estimateForTx')->willReturn('0x5208');

        $builder = $this->newBuilder(1, $this->ethRpcReturning('0x0'), $gas);
        $step    = $builder->nextStep($this->order(self::UUID, '100'));

        self::assertSame('0x5208', $this->txField($step->tx, 'gas'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function field(array $payload, string $section, string $key): string
    {
        $sub = $payload[$section] ?? null;
        $val = is_array($sub) ? ($sub[$key] ?? null) : null;
        if (!is_string($val)) {
            self::fail(sprintf('payload[%s][%s] is not a string', $section, $key));
        }

        return $val;
    }

    /**
     * @param array<string, mixed>|null $tx
     */
    private function txField(?array $tx, string $key): string
    {
        $val = $tx[$key] ?? null;
        if (!is_string($val)) {
            self::fail(sprintf('tx[%s] is not a string', $key));
        }

        return $val;
    }

    private function order(string $orderUuid, string $fromAmount, ?string $userAddress = self::USER): DepositTxOrderView
    {
        $wallet = new readonly class ($userAddress) implements UserWalletInterface {
            public function __construct(private ?string $userAddress) {}

            public function userAddress(): ?string
            {
                return $this->userAddress;
            }

            public function userJettonWallet(): ?string
            {
                return null;
            }
        };

        return new readonly class ($orderUuid, $fromAmount, $wallet) implements DepositTxOrderView {
            public function __construct(private string $orderUuid, private string $fromAmount, private UserWalletInterface $wallet) {}

            public function getId(): int
            {
                return 1;
            }

            public function getOrderId(): string
            {
                return $this->orderUuid;
            }

            public function getFromChain(): string
            {
                return 'usdt_erc20';
            }

            public function getDepositAddress(): string
            {
                return '0x1234567890ABCDEF1234567890ABCDEF12345678';
            }

            public function getFromAmount(): string
            {
                return $this->fromAmount;
            }

            public function getDepositMemo(): ?string
            {
                return null;
            }

            public function getUserWallet(): UserWalletInterface
            {
                return $this->wallet;
            }
        };
    }
}
