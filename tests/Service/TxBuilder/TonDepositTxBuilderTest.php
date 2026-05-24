<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\TonDepositTxBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TonDepositTxBuilder::class)]
final class TonDepositTxBuilderTest extends TestCase
{
    public function testSupportsTonOnly(): void
    {
        $builder = new TonDepositTxBuilder();
        self::assertTrue($builder->supports('ton'));
        self::assertFalse($builder->supports('usdt_jetton'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
    }

    public function testBuildEmitsTonConnectMessageShape(): void
    {
        $builder = new TonDepositTxBuilder();
        $payload = $builder->build($this->order('1.234567890', 'memo42'));

        self::assertSame('ton-native', $payload->kind);
        self::assertSame('UQbridge_ton_contract_address_padded_to_48_chars0', $this->field($payload->payload, 'address'));
        self::assertSame('1234567890', $this->field($payload->payload, 'amount'));
        self::assertNotEmpty($this->field($payload->payload, 'payload'));
        self::assertNotFalse(base64_decode($this->field($payload->payload, 'payload'), true));
    }

    public function testBuildConvertsHumanAmountToNanotonsViaBcmath(): void
    {
        $builder = new TonDepositTxBuilder();
        $payload = $builder->build($this->order('0.5', 'memo1'));

        self::assertSame('500000000', $this->field($payload->payload, 'amount'));
    }

    public function testBuildHandlesIntegerAmountWithoutFractionalDigits(): void
    {
        $builder = new TonDepositTxBuilder();
        $payload = $builder->build($this->order('10', 'memo7'));

        self::assertSame('10000000000', $this->field($payload->payload, 'amount'));
    }

    public function testBuildPayloadCellChangesWhenMemoChanges(): void
    {
        $builder  = new TonDepositTxBuilder();
        $payloadA = $builder->build($this->order('1.0', 'memo1'));
        $payloadB = $builder->build($this->order('1.0', 'memo2'));

        self::assertNotSame($this->field($payloadA->payload, 'payload'), $this->field($payloadB->payload, 'payload'));
    }

    private function order(string $fromAmount, string $memo): DepositTxOrderView
    {
        return new readonly class ($fromAmount, $memo) implements DepositTxOrderView {
            public function __construct(private string $fromAmount, private string $memo) {}

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

            public function getDepositAddress(): string
            {
                return 'UQbridge_ton_contract_address_padded_to_48_chars0';
            }

            public function getFromAmount(): string
            {
                return $this->fromAmount;
            }

            public function getDepositMemo(): string
            {
                return $this->memo;
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
