<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Gas;

use Amashukov\EthRpc\JsonRpcProviderInterface;
use Amashukov\BlockchainContextBundle\Service\Gas\EthGasFetcher;
use Amashukov\EthRpc\Vo\EthereumTransaction;
use Amashukov\EthRpc\Vo\EthereumTransactionReceipt;
use Amashukov\EthRpc\Vo\EthereumTxBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(EthGasFetcher::class)]
final class EthGasFetcherTest extends TestCase
{
    public function testFetchWeiReturnsBundleFee(): void
    {
        $rpc = $this->createMock(JsonRpcProviderInterface::class);
        $rpc->expects(self::once())
            ->method('getTypedTransaction')
            ->with('0xabc')
            ->willReturn($this->makeBundle('0xabc', [
                'status'            => '0x1',
                'gasUsed'           => '0x5208',
                'effectiveGasPrice' => '0x3b9aca00',
                'from'              => '0xX',
                'logs'              => [],
            ]));

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertSame('21000000000000', $fetcher->fetchWei('0xabc'));
    }

    public function testFetchWeiReturnsNullOnEmptyHash(): void
    {
        $rpc = $this->createMock(JsonRpcProviderInterface::class);
        $rpc->expects(self::never())->method('getTypedTransaction');

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchWei(''));
    }

    public function testFetchWeiReturnsNullWhenReceiptIsPending(): void
    {
        $rpc = $this->createStub(JsonRpcProviderInterface::class);
        $rpc->method('getTypedTransaction')->willReturn($this->makeBundle('0xabc', null));

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchWei('0xabc'));
    }

    public function testFetchWeiReturnsNullWhenGasUsedMissing(): void
    {
        $rpc = $this->createStub(JsonRpcProviderInterface::class);
        $rpc->method('getTypedTransaction')->willReturn($this->makeBundle('0xabc', [
            'status'            => '0x1',
            'effectiveGasPrice' => '0x3b9aca00',
            'from'              => '0xX',
            'logs'              => [],
        ]));

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchWei('0xabc'));
    }

    public function testFetchWeiReturnsNullWhenEffectiveGasPriceMissing(): void
    {
        $rpc = $this->createStub(JsonRpcProviderInterface::class);
        $rpc->method('getTypedTransaction')->willReturn($this->makeBundle('0xabc', [
            'status'  => '0x1',
            'gasUsed' => '0x5208',
            'from'    => '0xX',
            'logs'    => [],
        ]));

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchWei('0xabc'));
    }

    public function testFetchWeiSwallowsRpcException(): void
    {
        $rpc = $this->createStub(JsonRpcProviderInterface::class);
        $rpc->method('getTypedTransaction')->willThrowException(new RuntimeException('alchemy 503'));

        $fetcher = new EthGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchWei('0xabc'));
    }

    /**
     * @param null|array<string, mixed> $receipt
     */
    private function makeBundle(string $hash, ?array $receipt): EthereumTxBundle
    {
        return new EthereumTxBundle(
            transaction: EthereumTransaction::fromArray($hash, ['value' => '0x0']),
            receipt: EthereumTransactionReceipt::fromArray($hash, $receipt),
        );
    }
}
