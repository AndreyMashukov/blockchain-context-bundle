<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Gas;

use Amashukov\BlockchainContextBundle\Service\Gas\EthGasFetcherInterface;
use Amashukov\BlockchainContextBundle\Service\Gas\TonGasFetcherInterface;
use Amashukov\BlockchainContextBundle\Service\Gas\ZeroGasFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZeroGasFetcher::class)]
final class ZeroGasFetcherTest extends TestCase
{
    public function testImplementsBothEvmAndTonFetcherContracts(): void
    {
        $fetcher = new ZeroGasFetcher();

        self::assertInstanceOf(EthGasFetcherInterface::class, $fetcher);
        self::assertInstanceOf(TonGasFetcherInterface::class, $fetcher);
    }

    public function testFetchWeiAlwaysReturnsZero(): void
    {
        $fetcher = new ZeroGasFetcher();

        self::assertSame('0', $fetcher->fetchWei('0xabc'));
        self::assertSame('0', $fetcher->fetchWei(''));
    }

    public function testFetchFwdFeeNanoAlwaysReturnsZero(): void
    {
        $fetcher = new ZeroGasFetcher();

        self::assertSame('0', $fetcher->fetchFwdFeeNano('UQAbc', 'TXHASH'));
        self::assertSame('0', $fetcher->fetchFwdFeeNano('', ''));
    }
}
