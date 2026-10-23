<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinJsonDecoder;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinOutpoint;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcClient;
use Amashukov\BlockchainContextBundle\Tests\Helper\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class BitcoinRpcClientUtxoMethodsTest extends TestCase
{
    private const string TXID = '0000000000000000000000000000000000000000000000000000000000000001';

    private RecordingHttpClient $http;

    public function testCreatePsbtSendsExplicitInputsAndOutputs(): void
    {
        $psbt = $this->client('{"result":"cHNidP8=","error":null,"id":"bitcoin-core"}')
            ->createPsbt([new BitcoinOutpoint(self::TXID, 1)], [['bc1qexample' => '0.00100000']]);

        self::assertSame('cHNidP8=', $psbt);
        self::assertSame(['createpsbt', [[['txid' => self::TXID, 'vout' => 1]], [['bc1qexample' => '0.00100000']]]], $this->sent());
    }

    public function testGetTxOutReadsAnUnspentOutput(): void
    {
        $output = $this->client('{"result":{"bestblock":"00","confirmations":3,"value":0.00100000,"scriptPubKey":{"asm":"","hex":"0014abcd","address":"bc1qexample","type":"witness_v0_keyhash"},"coinbase":false},"error":null,"id":"bitcoin-core"}')
            ->getTxOut(self::TXID, 1);

        self::assertNotNull($output);
        self::assertSame('100000', $output->value->satoshis);
        self::assertSame('bc1qexample', $output->address);
        self::assertSame(3, $output->confirmations);
        self::assertSame(['gettxout', [self::TXID, 1, true]], $this->sent());
    }

    public function testGetTxOutIsNullForASpentOutput(): void
    {
        self::assertNull($this->client('{"result":null,"error":null,"id":"bitcoin-core"}')->getTxOut(self::TXID, 1));
    }

    public function testListUnspentIgnoresTokensOnBitcoinCore(): void
    {
        $this->client('{"result":[],"error":null,"id":"bitcoin-core"}')->listUnspent(1, 9999999, [], true);

        self::assertSame(['listunspent', [1, 9999999, [], true]], $this->sent());
    }

    private function client(string $body): BitcoinRpcClient
    {
        $factory    = new Psr17Factory();
        $this->http = new RecordingHttpClient($body);

        return new BitcoinRpcClient($this->http, $factory, $factory, 'http://bitcoin-node:8332/wallet/hot-wallet', 'rpc-user', 'rpc-password', new BitcoinJsonDecoder());
    }

    /**
     * @return array{mixed, mixed}
     */
    private function sent(): array
    {
        $payload = json_decode((string) $this->http->request?->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            return [null, null];
        }

        return [$payload['method'] ?? null, $payload['params'] ?? null];
    }
}
