<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\EthRpc\EthRpcClientInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\DepositTxView;
use Amashukov\BlockchainContextBundle\Service\Finality\EthBlockDepthCounter;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(EthBlockDepthCounter::class)]
final class EthBlockDepthCounterTest extends TestCase
{
    public function testSupportsEvmChains(): void
    {
        $counter = new EthBlockDepthCounter($this->createStub(EthRpcClientInterface::class), new ArrayAdapter());

        self::assertTrue($counter->supports('eth'));
        self::assertTrue($counter->supports('usdt_erc20'));
        self::assertFalse($counter->supports('ton'));
        self::assertFalse($counter->supports('stars'));
    }

    public function testCountReturnsHeadMinusBlockPlusOne(): void
    {
        $rpc = $this->createMock(EthRpcClientInterface::class);
        $rpc->expects(self::once())
            ->method('eth_blockNumber')
            ->willReturn(20);

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: '15', required: 12);
        self::assertSame(6, $counter->count($tx, $this->order()));
    }

    public function testCountCapsAtRequired(): void
    {
        $rpc = $this->createStub(EthRpcClientInterface::class);
        $rpc->method('eth_blockNumber')->willReturn(256);

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: '10', required: 12);
        self::assertSame(12, $counter->count($tx, $this->order()));
    }

    public function testCountReturnsZeroWhenBlockNumberMissing(): void
    {
        $rpc = $this->createMock(EthRpcClientInterface::class);
        $rpc->expects(self::never())->method('eth_blockNumber');

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: null, required: 12);
        self::assertSame(0, $counter->count($tx, $this->order()));
    }

    public function testCountReturnsZeroWhenRequiredZero(): void
    {
        $rpc = $this->createMock(EthRpcClientInterface::class);
        $rpc->expects(self::never())->method('eth_blockNumber');

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: '15', required: 0);
        self::assertSame(0, $counter->count($tx, $this->order()));
    }

    public function testCountReturnsZeroWhenHeadBelowDeposit(): void
    {
        $rpc = $this->createStub(EthRpcClientInterface::class);
        $rpc->method('eth_blockNumber')->willReturn(5);

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: '15', required: 12);
        self::assertSame(0, $counter->count($tx, $this->order()));
    }

    public function testCountReturnsZeroOnRpcOutageAndLogs(): void
    {
        $rpc = $this->createStub(EthRpcClientInterface::class);
        $rpc->method('eth_blockNumber')->willThrowException(new RuntimeException('alchemy down'));

        $counter = new EthBlockDepthCounter($rpc, new ArrayAdapter(), new NullLogger());

        $tx = $this->tx(blockNumber: '15', required: 12);
        self::assertSame(0, $counter->count($tx, $this->order()));
    }

    private function tx(?string $blockNumber, int $required): DepositTxView
    {
        return new readonly class ($blockNumber, $required) implements DepositTxView {
            public function __construct(
                private ?string $blockNumber,
                private int $required,
            ) {}

            public function getBlockNumber(): ?string
            {
                return $this->blockNumber;
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
                return null;
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
                return 'eth';
            }

            public function getOrderId(): string
            {
                return '00000000-0000-0000-0000-000000000000';
            }

            public function getIncomingTxHash(): ?string
            {
                return null;
            }
        };
    }
}
