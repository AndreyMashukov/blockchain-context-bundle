<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Gas;

use Amashukov\BlockchainContextBundle\Service\Gas\TonGasFetcher;
use Amashukov\Toncenter\ToncenterClientInterface;
use Amashukov\Toncenter\Vo\TonTransaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(TonGasFetcher::class)]
final class TonGasFetcherTest extends TestCase
{
    public function testFetchFwdFeeNanoReturnsMatchingTxFwdFee(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::once())
            ->method('getTypedTransactions')
            ->with('UQAbc...', ['limit' => 50])
            ->willReturn($this->typed('UQAbc...', [
                ['transaction_id' => ['hash' => 'other'], 'in_msg' => ['fwd_fee' => '999']],
                ['transaction_id' => ['hash' => 'TXHASH'], 'in_msg' => ['fwd_fee' => '1234567']],
            ]));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertSame('1234567', $fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    public function testFetchFwdFeeNanoReturnsNullOnEmptyArgs(): void
    {
        $rpc = $this->createMock(ToncenterClientInterface::class);
        $rpc->expects(self::never())->method('getTypedTransactions');

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchFwdFeeNano('', 'TXHASH'));
        self::assertNull($fetcher->fetchFwdFeeNano('UQAbc...', ''));
    }

    public function testFetchFwdFeeNanoReturnsNullWhenNoMatchingTx(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getTypedTransactions')->willReturn($this->typed('UQAbc...', [
            ['transaction_id' => ['hash' => 'other'], 'in_msg' => ['fwd_fee' => '999']],
        ]));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    public function testFetchFwdFeeNanoReturnsNullWhenFwdFeeMissing(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getTypedTransactions')->willReturn($this->typed('UQAbc...', [
            ['transaction_id' => ['hash' => 'TXHASH'], 'in_msg' => []],
        ]));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    public function testFetchFwdFeeNanoReturnsNullWhenFwdFeeNonNumeric(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getTypedTransactions')->willReturn($this->typed('UQAbc...', [
            ['transaction_id' => ['hash' => 'TXHASH'], 'in_msg' => ['fwd_fee' => 'garbage']],
        ]));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    public function testFetchFwdFeeNanoAcceptsIntFwdFee(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getTypedTransactions')->willReturn($this->typed('UQAbc...', [
            ['transaction_id' => ['hash' => 'TXHASH'], 'in_msg' => ['fwd_fee' => 7654321]],
        ]));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertSame('7654321', $fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    public function testFetchFwdFeeNanoSwallowsRpcException(): void
    {
        $rpc = $this->createStub(ToncenterClientInterface::class);
        $rpc->method('getTypedTransactions')->willThrowException(new RuntimeException('toncenter 429'));

        $fetcher = new TonGasFetcher($rpc, new NullLogger());
        self::assertNull($fetcher->fetchFwdFeeNano('UQAbc...', 'TXHASH'));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<TonTransaction>
     */
    private function typed(string $address, array $rows): array
    {
        return array_map(static fn(array $row): TonTransaction => TonTransaction::fromToncenter($row, $address), $rows);
    }
}
