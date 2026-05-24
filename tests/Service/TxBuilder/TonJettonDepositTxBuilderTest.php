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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TonJettonDepositTxBuilder::class)]
final class TonJettonDepositTxBuilderTest extends TestCase
{
    private const string BRIDGE_CONTRACT       = '0:f9f2a4e1821d11fe62f7d8bf846b0a29ba29bea404869f00c24b352016e7ea8d';

    private const string BRIDGE_CONTRACT_OTHER = '0:00000000000000000000000000000000000000000000000000000000000000ff';

    private const string USER_ADDRESS          = '0:0000000000000000000000000000000000000000000000000000000000000002';

    private const string USER_JETTON_WALLET    = '0:0000000000000000000000000000000000000000000000000000000000000003';

    private const string EXPECTED_OUTER_NANO   = '100000000';

    private const int EXPECTED_FORWARD_NANO    = 50_000_000;

    public function testSupportsUsdtJettonOnly(): void
    {
        $builder = new TonJettonDepositTxBuilder(self::BRIDGE_CONTRACT);
        self::assertTrue($builder->supports('usdt_jetton'));
        self::assertFalse($builder->supports('ton'));
        self::assertFalse($builder->supports('eth'));
        self::assertFalse($builder->supports('usdt_erc20'));
    }

    public function testBuildEmitsTonConnectMessageShapeWithBridgeContractEnvAndHundredMillionOuter(): void
    {
        $payload = $this->buildPayload('100', 'memo42');

        self::assertSame('ton-jetton', $payload->kind);
        self::assertSame(self::USER_JETTON_WALLET, $this->field($payload->payload, 'address'));
        self::assertSame(self::EXPECTED_OUTER_NANO, $this->field($payload->payload, 'amount'));
        self::assertNotFalse(base64_decode($this->field($payload->payload, 'payload'), true));
    }

    public function testBuildRejectsMissingUserAddress(): void
    {
        $builder = new TonJettonDepositTxBuilder(self::BRIDGE_CONTRACT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('userAddress + userJettonWallet are required');

        $builder->build($this->order('100', 'memo42'), [
            'userJettonWallet' => self::USER_JETTON_WALLET,
        ]);
    }

    public function testBuildRejectsMissingUserJettonWallet(): void
    {
        $builder = new TonJettonDepositTxBuilder(self::BRIDGE_CONTRACT);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('userAddress + userJettonWallet are required');

        $builder->build($this->order('100', 'memo42'), [
            'userAddress' => self::USER_ADDRESS,
        ]);
    }

    public function testBuildRejectsMissingBridgeContract(): void
    {
        $builder = new TonJettonDepositTxBuilder('');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BRIDGE_TON_CONTRACT env not configured');

        $builder->build($this->order('100', 'memo42'), [
            'userAddress'      => self::USER_ADDRESS,
            'userJettonWallet' => self::USER_JETTON_WALLET,
        ]);
    }

    public function testBuildBodyEncodesDestinationAsBridgeContractEnvNotJettonWalletAddress(): void
    {
        $payloadContractA = $this->buildPayloadWithBridge(self::BRIDGE_CONTRACT, '0.90', 'memo42');
        $payloadContractB = $this->buildPayloadWithBridge(self::BRIDGE_CONTRACT_OTHER, '0.90', 'memo42');

        self::assertNotSame(
            $this->field($payloadContractA->payload, 'payload'),
            $this->field($payloadContractB->payload, 'payload'),
            'Different BRIDGE_TON_CONTRACT values MUST produce different BOC bodies (destination field is wired from the ctor arg). '
            . 'Regression net for prod-incident dfb57941: BRIDGE_USDT_JETTON_WALLET was placed in `destination`, routing funds to '
            . 'a jetton-of-jetton wallet (EQBlRnao66S8uC64CJuzqX9e3-F0PlPdH0IUS7ZDkThXAEvS) instead of the bridge contract.',
        );

        $expectedBoc = $this->buildExpectedBodyBoc(
            bridgeContract: self::BRIDGE_CONTRACT,
            userAddress: self::USER_ADDRESS,
            fromAmountHuman: '0.90',
            memo: 'memo42',
            forwardTonAmount: self::EXPECTED_FORWARD_NANO,
        );
        self::assertSame($expectedBoc, $this->field($payloadContractA->payload, 'payload'));
    }

    public function testBuildBodyStoresForwardTonAmountAtFiftyMillionNanoton(): void
    {
        $payload = $this->buildPayload('0.90', 'memo42');

        $bocFifty = $this->buildExpectedBodyBoc(
            bridgeContract: self::BRIDGE_CONTRACT,
            userAddress: self::USER_ADDRESS,
            fromAmountHuman: '0.90',
            memo: 'memo42',
            forwardTonAmount: 50_000_000,
        );
        $bocOne = $this->buildExpectedBodyBoc(
            bridgeContract: self::BRIDGE_CONTRACT,
            userAddress: self::USER_ADDRESS,
            fromAmountHuman: '0.90',
            memo: 'memo42',
            forwardTonAmount: 1,
        );

        self::assertSame($bocFifty, $this->field($payload->payload, 'payload'), 'Production builder MUST encode forward_ton_amount = 50_000_000 (0.05 TON). '
            . 'Regression net for prod-incident dfb57941: forward_ton_amount = 1 nano = below network fwd_fee = no transfer_notification = stuck order.');
        self::assertNotSame($bocOne, $this->field($payload->payload, 'payload'), 'forward_ton_amount = 1 nano is the prod-incident value and MUST NOT match production output.');
    }

    public function testBuildOuterMessageValueAtHundredMillionNanoton(): void
    {
        $payload = $this->buildPayload('0.90', 'memo42');
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
        $a = $this->buildPayload('0.9', 'memoX');
        $b = $this->buildPayload('9', 'memoX');
        self::assertNotSame($this->field($a->payload, 'payload'), $this->field($b->payload, 'payload'), 'amount=0.9 and amount=9 must produce distinct BOC (rules out collapsed scaling)');
    }

    public function testBuildPayloadChangesWhenAmountChanges(): void
    {
        $payloadA = $this->buildPayload('1.5', 'memo1');
        $payloadB = $this->buildPayload('15', 'memo1');
        self::assertNotSame($this->field($payloadA->payload, 'payload'), $this->field($payloadB->payload, 'payload'));
    }

    public function testBuildPayloadChangesWhenMemoChanges(): void
    {
        $payloadA = $this->buildPayload('1.5', 'memo1');
        $payloadB = $this->buildPayload('1.5', 'memo99');
        self::assertNotSame($this->field($payloadA->payload, 'payload'), $this->field($payloadB->payload, 'payload'));
    }

    private function buildPayload(string $fromAmount, string $memo): DepositTxPayload
    {
        return $this->buildPayloadWithBridge(self::BRIDGE_CONTRACT, $fromAmount, $memo);
    }

    private function buildPayloadWithBridge(string $bridgeContractAddress, string $fromAmount, string $memo): DepositTxPayload
    {
        $builder = new TonJettonDepositTxBuilder($bridgeContractAddress);

        return $builder->build($this->order($fromAmount, $memo), [
            'userAddress'      => self::USER_ADDRESS,
            'userJettonWallet' => self::USER_JETTON_WALLET,
        ]);
    }

    private function buildExpectedBodyBoc(
        string $bridgeContract,
        string $userAddress,
        string $fromAmountHuman,
        string $memo,
        int $forwardTonAmount,
    ): string {
        $forwardCell = (new Builder())
            ->storeUint(0, 32)
            ->storeStringTail($memo)
            ->endCell();

        $body = (new Builder())
            ->storeUint(0x0F8A7EA5, 32)
            ->storeUint(0, 64)
            ->storeCoins(UsdtJettonDecimals::toAtomic($fromAmountHuman))
            ->storeAddress(Address::parse($bridgeContract)->toCellData())
            ->storeAddress(Address::parse($userAddress)->toCellData())
            ->storeBit(0)
            ->storeCoins($forwardTonAmount)
            ->storeBit(1)
            ->storeRef($forwardCell)
            ->endCell();

        return Boc::encodeBase64($body);
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
