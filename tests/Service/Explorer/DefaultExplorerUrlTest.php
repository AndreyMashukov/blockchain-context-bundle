<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Explorer;

use Amashukov\BlockchainContextBundle\Service\Explorer\DefaultExplorerUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultExplorerUrlTest extends TestCase
{
    private const string TXID = '0000000000000000000000000000000000000000000000000000000000000001';

    private const string ADDRESS = 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function txCases(): iterable
    {
        yield 'bitcoin' => ['btc', self::TXID, 'https://mempool.space/tx/' . self::TXID];
        yield 'ether' => ['eth', '0xabc', 'https://etherscan.io/tx/0xabc'];
        yield 'usdt erc20' => ['usdt_erc20', '0xabc', 'https://etherscan.io/tx/0xabc'];
        yield 'ton' => ['ton', 'abc', 'https://tonscan.org/tx/abc'];
        yield 'usdt jetton' => ['usdt_jetton', 'abc', 'https://tonscan.org/tx/abc'];
    }

    #[DataProvider('txCases')]
    public function testTransactionLinkPointsAtTheChainExplorer(string $chain, string $hash, string $expected): void
    {
        self::assertSame($expected, (new DefaultExplorerUrl())->forTx($chain, $hash));
    }

    public function testBitcoinAddressLinkPointsAtTheBitcoinExplorer(): void
    {
        self::assertSame('https://mempool.space/address/' . self::ADDRESS, (new DefaultExplorerUrl())->forAddress('btc', self::ADDRESS));
    }

    public function testConfiguredBitcoinExplorerReplacesTheDefault(): void
    {
        $explorer = new DefaultExplorerUrl(bitcoinExplorer: 'https://mempool.space/testnet4');

        self::assertSame('https://mempool.space/testnet4/tx/' . self::TXID, $explorer->forTx('btc', self::TXID));
    }

    public function testEmptyBitcoinExplorerYieldsNoLink(): void
    {
        $explorer = new DefaultExplorerUrl(bitcoinExplorer: '');

        self::assertNull($explorer->forTx('btc', self::TXID));
        self::assertNull($explorer->forAddress('btc', self::ADDRESS));
    }

    public function testUnknownChainAndEmptyHashYieldNoLink(): void
    {
        $explorer = new DefaultExplorerUrl();

        self::assertNull($explorer->forTx('stars', 'abc'));
        self::assertNull($explorer->forTx('btc', ''));
    }
}
