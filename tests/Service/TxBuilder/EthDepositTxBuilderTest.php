<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\AbiEncoder\AbiEncoder;
use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\EthDepositTxBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EthDepositTxBuilder::class)]
final class EthDepositTxBuilderTest extends TestCase
{
    private const string UUID = 'f8a3b2c1-4d5e-6789-abcd-ef0123456789';

    private const string UUID_HEX = 'f8a3b2c14d5e6789abcdef0123456789';

    private function newBuilder(int $chainId): EthDepositTxBuilder
    {
        return new EthDepositTxBuilder(chainId: $chainId, uuidIntCodec: new UuidIntCodec());
    }

    public function testSupportsEthOnly(): void
    {
        $builder = $this->newBuilder(1);
        self::assertTrue($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
    }

    public function testBuildEmitsEthSendTransactionShape(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '1.5'));

        self::assertSame('evm-native', $payload->kind);
        self::assertSame('0xabcdef0123456789abcdef0123456789abcdef01', $this->field($payload->payload, 'to'));
        self::assertSame('0x1', $this->field($payload->payload, 'chainId'));
        self::assertSame('0x14d1120d7b160000', $this->field($payload->payload, 'value'));
    }

    public function testBuildSelectorMatchesDepositForBridgeUint256(): void
    {
        $expectedSelector = '0x' . AbiEncoder::methodId('depositForBridge(uint256)');

        $builder = $this->newBuilder(11155111);
        $payload = $builder->build($this->order(self::UUID, '0.01'));

        self::assertStringStartsWith($expectedSelector, $this->field($payload->payload, 'data'));
        self::assertSame(10 + 64, \strlen($this->field($payload->payload, 'data')));
        self::assertSame('0xaa36a7', $this->field($payload->payload, 'chainId'));
    }

    public function testBuildEncodesUuidAsUint256IntoCalldataTail(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '0.01'));

        self::assertStringEndsWith(str_pad(self::UUID_HEX, 64, '0', \STR_PAD_LEFT), $this->field($payload->payload, 'data'));
    }

    public function testBuildHandlesSmallSubGweiValuesPrecisely(): void
    {
        $builder = $this->newBuilder(1);
        $payload = $builder->build($this->order(self::UUID, '0.000000000000000001'));

        self::assertSame('0x1', $this->field($payload->payload, 'value'));
    }

    public function testBuildEncodingChangesWhenUuidChanges(): void
    {
        $builder  = $this->newBuilder(1);
        $payloadA = $builder->build($this->order('11111111-1111-1111-1111-111111111111', '0.01'));
        $payloadB = $builder->build($this->order('22222222-2222-2222-2222-222222222222', '0.01'));

        self::assertNotSame($this->field($payloadA->payload, 'data'), $this->field($payloadB->payload, 'data'));
    }

    private function order(string $orderUuid, string $fromAmount): DepositTxOrderView
    {
        return new readonly class ($orderUuid, $fromAmount) implements DepositTxOrderView {
            public function __construct(private string $orderUuid, private string $fromAmount) {}

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
                return 'eth';
            }

            public function getDepositAddress(): string
            {
                return '0xABCDEF0123456789aBcDeF0123456789AbCdEf01';
            }

            public function getFromAmount(): string
            {
                return $this->fromAmount;
            }

            public function getDepositMemo(): ?string
            {
                return null;
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function field(array $payload, string $key): string
    {
        $val = $payload[$key] ?? null;
        if (!is_string($val)) {
            self::fail(sprintf('payload[%s] is not a string', $key));
        }

        return $val;
    }
}
