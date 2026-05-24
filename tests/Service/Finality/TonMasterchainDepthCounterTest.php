<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\DepositTxView;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use Amashukov\BlockchainContextBundle\Service\Finality\TonMasterchainDepthCounter;
use Amashukov\Toncenter\TonRpcException;
use Amashukov\Toncenter\ToncenterClientInterface;
use Amashukov\Toncenter\Vo\TonBlockId;
use Amashukov\Toncenter\Vo\TonMasterchainInfo;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(TonMasterchainDepthCounter::class)]
final class TonMasterchainDepthCounterTest extends TestCase
{
    public function testSupportsTonAndUsdtJettonOnly(): void
    {
        $counter = new TonMasterchainDepthCounter($this->createStub(ToncenterClientInterface::class), new ArrayAdapter());
        self::assertTrue($counter->supports('ton'));
        self::assertTrue($counter->supports('usdt_jetton'));
        self::assertFalse($counter->supports('eth'));
        self::assertFalse($counter->supports('usdt_erc20'));
        self::assertFalse($counter->supports('stars'));
    }

    public function testReturnsZeroWhenBaselineMissing(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::never())->method('getMasterchainInfo');

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter());
        self::assertSame(0, $counter->count($this->tx(mcSeqno: null, required: 10), $this->order()));
    }

    public function testReturnsZeroWhenHeadEqualsBaseline(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getMasterchainInfo')->willReturn($this->mcInfo(seqno: 100));

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter());
        self::assertSame(0, $counter->count($this->tx(mcSeqno: 100, required: 10), $this->order()));
    }

    public function testCountsHeadMinusBaseline(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getMasterchainInfo')->willReturn($this->mcInfo(seqno: 107));

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter());
        self::assertSame(7, $counter->count($this->tx(mcSeqno: 100, required: 10), $this->order()));
    }

    public function testCapsAtRequired(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getMasterchainInfo')->willReturn($this->mcInfo(seqno: 200));

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter());
        self::assertSame(10, $counter->count($this->tx(mcSeqno: 100, required: 10), $this->order()));
    }

    public function testReturnsZeroOnTonRpcException(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getMasterchainInfo')->willThrowException(new TonRpcException('toncenter outage', 503));

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter(), new NullLogger());
        self::assertSame(0, $counter->count($this->tx(mcSeqno: 100, required: 10), $this->order()));
    }

    public function testReturnsZeroWhenRequiredNotSet(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::never())->method('getMasterchainInfo');

        $counter = new TonMasterchainDepthCounter($rpc, new ArrayAdapter());
        self::assertSame(0, $counter->count($this->tx(mcSeqno: 100, required: 0), $this->order()));
    }

    private function tx(?int $mcSeqno, int $required): DepositTxView
    {
        return new readonly class ($mcSeqno, $required) implements DepositTxView {
            public function __construct(
                private ?int $mcSeqno,
                private int $required,
            ) {}

            public function getBlockNumber(): ?string
            {
                return null;
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getConfirmationsRequired(): int
            {
                return $this->required;
            }

            public function getMcSeqno(): ?int
            {
                return $this->mcSeqno;
            }
        };
    }

    private function order(): OrderView
    {
        return new class implements OrderView {
            public function getCreatedAt(): DateTimeInterface
            {
                return new DateTimeImmutable();
            }

            public function getFromChain(): string
            {
                return 'ton';
            }

            public function getOrderId(): string
            {
                return 'order-1';
            }

            public function getIncomingTxHash(): ?string
            {
                return null;
            }
        };
    }

    private function mcInfo(int $seqno): TonMasterchainInfo
    {
        $blockId = new TonBlockId(workchain: -1, shard: '-9223372036854775808', seqno: $seqno, rootHash: 'r', fileHash: 'f');

        return new TonMasterchainInfo(last: $blockId, init: $blockId, stateRootHash: 's');
    }
}
