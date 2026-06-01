<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\Numeric\UsdtJettonDecimals;
use Amashukov\TonWallet\Address;
use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxOrderView;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxPayload;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\TonJettonDepositTxBuilder;
use Amashukov\BlockchainContextBundle\Service\TxBuilder\VaultInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TonJettonDepositTxBuilder::class)]
final class TonJettonDepositTxBuilderTest extends TestCase
{
    private const string VAULT       = '0:f9f2a4e1821d11fe62f7d8bf846b0a29ba29bea404869f00c24b352016e7ea8d';

    private const string VAULT_OTHER = '0:00000000000000000000000000000000000000000000000000000000000000ff';

    private const string EXPECTED_OUTER_NANO = '100000000';

    private const int EXPECTED_FORWARD_NANO   = 50_000_000;

    public function testSupportsUsdtJettonOnly(): void
    {
        $builder = new TonJettonDepositTxBuilder();
        self::assertTrue($builder->supports('usdt_jetton'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
    }

    public function testBuildEmitsTonConnectMessageShapeWithVaultAddressAndHundredMillionOuter(): void
    {
        $payload = $this->buildPayload(self::VAULT, '100', 'memo42');

        self::assertSame('ton-jetton', $payload->kind);
        self::assertSame(self::VAULT, $this->field($payload->payload, 'address'));
        self::assertSame(self::EXPECTED_OUTER_NANO, $this->field($payload->payload, 'amount'));
        self::assertNotFalse(base64_decode($this->field($payload->payload, 'payload'), true));
    }

    public function testBuildBodyEncodesDestinationAsVaultAddress(): void
    {
        $payloadA = $this->buildPayload(self::VAULT, '0.90', 'memo42');
        $payloadB = $this->buildPayload(self::VAULT_OTHER, '0.90', 'memo42');

        self::assertNotSame(
            $this->field($payloadA->payload, 'payload'),
            $this->field($payloadB->payload, 'payload'),
            'Different vault (our contract) addresses MUST produce different BOC bodies — destination is wired from the order vault. '
            . 'Regression net for prod-incident dfb57941: a wrong destination routed funds to a jetton-of-jetton wallet instead of the contract.',
        );

        $expectedBoc = $this->buildExpectedBodyBoc(self::VAULT, '0.90', 'memo42', self::EXPECTED_FORWARD_NANO);
        self::assertSame($expectedBoc, $this->field($payloadA->payload, 'payload'));
    }

    public function testBuildBodyStoresForwardTonAmountAtFiftyMillionNanoton(): void
    {
        $payload = $this->buildPayload(self::VAULT, '0.90', 'memo42');

        $bocFifty = $this->buildExpectedBodyBoc(self::VAULT, '0.90', 'memo42', 50_000_000);
        $bocOne   = $this->buildExpectedBodyBoc(self::VAULT, '0.90', 'memo42', 1);

        self::assertSame($bocFifty, $this->field($payload->payload, 'payload'), 'Production builder MUST encode forward_ton_amount = 50_000_000 (0.05 TON). '
            . 'Regression net for prod-incident dfb57941: forward_ton_amount = 1 nano = below network fwd_fee = no transfer_notification = stuck order.');
        self::assertNotSame($bocOne, $this->field($payload->payload, 'payload'), 'forward_ton_amount = 1 nano is the prod-incident value and MUST NOT match production output.');
    }

    public function testBuildOuterMessageValueAtHundredMillionNanoton(): void
    {
        $payload = $this->buildPayload(self::VAULT, '0.90', 'memo42');
        self::assertSame(self::EXPECTED_OUTER_NANO, $this->field($payload->payload, 'amount'));
    }

    public function testBuildForwardTonAmountIsAboveNetworkFwdFeeMinimum(): void
    {
        self::assertGreaterThanOrEqual(
            10_000_000,
            self::EXPECTED_FORWARD_NANO,
            '10_000_000 nanoTON (0.01 TON) is the safety floor: ~1000x the empirical network fwd_fee (~10k nano). '
            . 'Below this, bridge jetton-wallet cannot send transfer_notification.',
        );
    }

    public function testBuildOuterMessageCoversForwardTonAmountPlusUserJettonWalletProcessingGas(): void
    {
        $minOuter = self::EXPECTED_FORWARD_NANO + 30_000_000;
        self::assertGreaterThanOrEqual(
            (string) $minOuter,
            self::EXPECTED_OUTER_NANO,
            'Outer envelope MUST cover forward_ton_amount + ~30M nano for user jetton-wallet processing gas. '
            . 'Lower values risk user-wallet "insufficient balance" rejection.',
        );
    }

    public function testBuildAmountTenXChangeProducesDistinctBoc(): void
    {
        $a = $this->buildPayload(self::VAULT, '0.9', 'memoX');
        $b = $this->buildPayload(self::VAULT, '9', 'memoX');
        self::assertNotSame($this->field($a->payload, 'payload'), $this->field($b->payload, 'payload'), 'amount=0.9 and amount=9 must produce distinct BOC (rules out collapsed scaling)');
    }

    public function testBuildPayloadChangesWhenAmountChanges(): void
    {
        $payloadA = $this->buildPayload(self::VAULT, '1.5', 'memo1');
        $payloadB = $this->buildPayload(self::VAULT, '15', 'memo1');
        self::assertNotSame($this->field($payloadA->payload, 'payload'), $this->field($payloadB->payload, 'payload'));
    }

    public function testBuildPayloadChangesWhenMemoChanges(): void
    {
        $payloadA = $this->buildPayload(self::VAULT, '1.5', 'memo1');
        $payloadB = $this->buildPayload(self::VAULT, '1.5', 'memo99');
        self::assertNotSame($this->field($payloadA->payload, 'payload'), $this->field($payloadB->payload, 'payload'));
    }

    private function buildPayload(string $vault, string $fromAmount, string $memo): DepositTxPayload
    {
        return (new TonJettonDepositTxBuilder())->build($this->order($vault, $fromAmount, $memo));
    }

    private function buildExpectedBodyBoc(string $vault, string $fromAmountHuman, string $memo, int $forwardTonAmount): string
    {
        $forwardCell = (new Builder())
            ->storeUint(0, 32)
            ->storeStringTail($memo)
            ->endCell();

        $body = (new Builder())
            ->storeUint(0x0F8A7EA5, 32)
            ->storeUint(0, 64)
            ->storeCoins(UsdtJettonDecimals::toAtomic($fromAmountHuman))
            ->storeAddress(Address::parse($vault)->toCellData())
            ->storeAddress(Address::parse($vault)->toCellData())
            ->storeBit(0)
            ->storeCoins($forwardTonAmount)
            ->storeBit(1)
            ->storeRef($forwardCell)
            ->endCell();

        return Boc::encodeBase64($body);
    }

    private function order(string $vault, string $fromAmount, string $memo): DepositTxOrderView
    {
        $vaultVo = new readonly class ($vault) implements VaultInterface {
            public function __construct(private string $address) {}

            public function getAddress(): string
            {
                return $this->address;
            }
        };

        return new readonly class ($fromAmount, $memo, $vaultVo) implements DepositTxOrderView {
            public function __construct(private string $fromAmount, private string $memo, private VaultInterface $vault) {}

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
                return 'usdt_jetton';
            }

            public function getDepositAddress(): ?string
            {
                return null;
            }

            public function getFromAmount(): string
            {
                return $this->fromAmount;
            }

            public function getDepositMemo(): string
            {
                return $this->memo;
            }

            public function getVault(): VaultInterface
            {
                return $this->vault;
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
