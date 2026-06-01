<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\Erc20DepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\SignerInterface;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\VaultInterface;
use Amashukov\EthRpc\EthRpcClientInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Erc20DepositTxBuilder::class)]
final class Erc20DepositTxBuilderTest extends TestCase
{
    private const string USDT = '0xdAC17F958D2ee523a2206206994597C13D831ec7';

    private const string VAULT = '0x1234567890ABCDEF1234567890ABCDEF12345678';

    private const string SIGNER = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string UUID = 'f8a3b2c1-4d5e-6789-abcd-ef0123456789';

    private const string UUID_HEX = 'f8a3b2c14d5e6789abcdef0123456789';

    private const string BIG = '0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    private const string ZERO = '0x0';

    private function newBuilder(int $chainId, ?EthRpcClientInterface $ethRpc = null): Erc20DepositTxBuilder
    {
        return new Erc20DepositTxBuilder(
            usdtTokenAddress: self::USDT,
            chainId: $chainId,
            uuidIntCodec: new UuidIntCodec(),
            ethRpc: $ethRpc ?? $this->ethRpc(self::BIG, self::BIG),
        );
    }

    private function ethRpc(string $balanceHex, string $allowanceHex): EthRpcClientInterface
    {
        $rpc = $this->createStub(EthRpcClientInterface::class);
        $rpc->method('eth_call')->willReturnCallback(
            static fn(array $tx): string => str_starts_with((string) ($tx['data'] ?? ''), '0x70a08231') ? $balanceHex : $allowanceHex,
        );

        return $rpc;
    }

    private function signer(string $address = self::SIGNER): SignerInterface
    {
        return new readonly class ($address) implements SignerInterface {
            public function __construct(private string $address) {}

            public function getAddress(): string
            {
                return $this->address;
            }
        };
    }

    public function testSupportsUsdtErc20Only(): void
    {
        $builder = $this->newBuilder(1);
        self::assertTrue($builder->supports('usdt_erc20'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
    }

    public function testBuildEmitsApprovePlusDepositPairTargetingVault(): void
    {
        $payload = $this->newBuilder(1)->build($this->order(self::UUID, '100'));

        self::assertSame('evm-erc20', $payload->kind);
        self::assertSame(strtolower(self::USDT), $this->field($payload->payload, 'approve', 'to'));
        self::assertSame(strtolower(self::VAULT), $this->field($payload->payload, 'deposit', 'to'));
        self::assertSame('0x1', $this->field($payload->payload, 'approve', 'chainId'));
        self::assertSame('0x1', $this->field($payload->payload, 'deposit', 'chainId'));
    }

    public function testApproveSpenderIsTheVault(): void
    {
        $payload     = $this->newBuilder(1)->build($this->order(self::UUID, '100'));
        $vaultPadded = str_pad(strtolower(substr(self::VAULT, 2)), 64, '0', \STR_PAD_LEFT);
        self::assertStringContainsString($vaultPadded, $this->field($payload->payload, 'approve', 'data'));
    }

    public function testDepositCalldataMatchesBridgeSelectorAndUuidTail(): void
    {
        $payload = $this->newBuilder(1)->build($this->order(self::UUID, '1.5'));

        self::assertStringStartsWith('0x' . AbiEncoder::methodId('depositTokenForBridge(address,uint256,uint256)'), $this->field($payload->payload, 'deposit', 'data'));
        self::assertStringEndsWith(str_pad(self::UUID_HEX, 64, '0', \STR_PAD_LEFT), $this->field($payload->payload, 'deposit', 'data'));
    }

    public function testAmountConvertsToSixDecimalUnits(): void
    {
        $payload = $this->newBuilder(1)->build($this->order(self::UUID, '1.5'));
        self::assertSame(str_pad('16e360', 64, '0', \STR_PAD_LEFT), substr($this->field($payload->payload, 'approve', 'data'), -64));
    }

    public function testNextStepReturnsApproveWhenAllowanceInsufficient(): void
    {
        $builder = $this->newBuilder(1, $this->ethRpc(self::BIG, self::ZERO));
        $step    = $builder->nextStep($this->order(self::UUID, '100'), $this->signer());

        self::assertSame('evm-approve', $step->kind);
        self::assertSame(strtolower(self::USDT), $this->txField($step->tx, 'to'));
    }

    public function testNextStepReturnsDepositWhenAllowanceSufficient(): void
    {
        $builder = $this->newBuilder(1, $this->ethRpc(self::BIG, self::BIG));
        $step    = $builder->nextStep($this->order(self::UUID, '100'), $this->signer());

        self::assertSame('evm-deposit-erc20', $step->kind);
        self::assertSame(strtolower(self::VAULT), $this->txField($step->tx, 'to'));
    }

    public function testNextStepThrowsWhenSignerBalanceInsufficient(): void
    {
        $builder = $this->newBuilder(1, $this->ethRpc(self::ZERO, self::BIG));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signer USDT balance');

        $builder->nextStep($this->order(self::UUID, '100'), $this->signer());
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

    private function order(string $orderUuid, string $fromAmount): DepositTxOrderView
    {
        $vaultAddr = self::VAULT;
        $vault     = new readonly class ($vaultAddr) implements VaultInterface {
            public function __construct(private string $address) {}

            public function getAddress(): string
            {
                return $this->address;
            }
        };

        return new readonly class ($orderUuid, $fromAmount, $vault, $vaultAddr) implements DepositTxOrderView {
            public function __construct(private string $orderUuid, private string $fromAmount, private VaultInterface $vault, private string $vaultAddr) {}

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
                return $this->vaultAddr;
            }

            public function getFromAmount(): string
            {
                return $this->fromAmount;
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
