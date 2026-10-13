<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinJsonDecoder;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcClient;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcException;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcValue;
use Amashukov\BlockchainContextBundle\Tests\Helper\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BitcoinTimestampTest extends TestCase
{
    public function testBothListsPreserveTheirIndependentTimestamps(): void
    {
        $client = $this->client('{"result":{"transactions":[{"txid":"a","vout":0,"category":"receive","address":"bc1q","amount":0.01,"confirmations":2,"blockhash":"b","blocktime":1791882000,"timereceived":1791881000},{"txid":"c","vout":1,"category":"receive","amount":0.02,"confirmations":0,"timereceived":1791883000}],"removed":[{"txid":"d","vout":0,"category":"receive","amount":0.01,"confirmations":-1,"blocktime":1791880000,"timereceived":1791879000}],"lastblock":"tip"},"error":null}');
        $result = $client->listSinceBlock(null);
        self::assertSame(1791882000, $result->transactions[0]->blockTime);
        self::assertSame(1791881000, $result->transactions[0]->timeReceived);
        self::assertNull($result->transactions[1]->blockTime);
        self::assertSame(1791883000, $result->transactions[1]->timeReceived);
        self::assertSame(1791880000, $result->removed[0]->blockTime);
        self::assertSame(1791879000, $result->removed[0]->timeReceived);
    }

    public function testGetTransactionAndLegacyMissingFields(): void
    {
        $tx = $this->client('{"result":{"txid":"a","amount":0.01,"confirmations":1,"hex":"00","blocktime":1791882000,"timereceived":1791881000},"error":null}')->getTransaction('a');
        self::assertSame(1791882000, $tx->blockTime);
        self::assertSame(1791881000, $tx->timeReceived);
        $legacy = $this->client('{"result":{"txid":"a","amount":0.01,"confirmations":0,"hex":"00"},"error":null}')->getTransaction('a');
        self::assertNull($legacy->blockTime);
        self::assertNull($legacy->timeReceived);
        self::assertNull(BitcoinRpcValue::nullableTimestamp(['time' => null], 'time'));
        self::assertSame(0, BitcoinRpcValue::nullableTimestamp(['time' => '0'], 'time'));
        self::assertSame(PHP_INT_MAX, BitcoinRpcValue::nullableTimestamp(['time' => (string) PHP_INT_MAX], 'time'));
    }

    #[DataProvider('invalidTimestamps')]
    public function testMalformedTimestampsFailClosed(mixed $value): void
    {
        $this->expectException(BitcoinRpcException::class);
        BitcoinRpcValue::nullableTimestamp(['time' => $value], 'time');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidTimestamps(): iterable
    {
        foreach (['negative' => '-1', 'fraction' => '1.5', 'exponent' => '1e3', 'overflow' => '9223372036854775808', 'empty' => '', 'space' => ' 1', 'boolean' => true, 'array' => [], 'native integer' => 1] as $name => $value) {
            yield $name => [$value];
        }
    }

    private function client(string $body): BitcoinRpcClient
    {
        $factory = new Psr17Factory();
        return new BitcoinRpcClient(new RecordingHttpClient($body), $factory, $factory, 'http://localhost', 'user', 'password', new BitcoinJsonDecoder());
    }
}
