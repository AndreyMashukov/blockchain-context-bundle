<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinJsonDecoder;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcClient;
use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinRpcException;
use Amashukov\BlockchainContextBundle\Tests\Helper\RecordingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use PHPUnit\Framework\TestCase;

final class BitcoinRpcClientTest extends TestCase
{
    public function testListUnspentReturnsTypedExactAmounts(): void
    {
        $http   = $this->http('{"result":[{"txid":"abc","vout":1,"address":"bc1qexample","amount":0.20000001,"confirmations":7,"spendable":true,"solvable":true,"safe":true}],"error":null,"id":"bitcoin-core"}');
        $client = $this->client($http);

        $utxos = $client->listUnspent();

        self::assertCount(1, $utxos);
        self::assertSame('20000001', $utxos[0]->amount->satoshis);
        self::assertSame('abc', $utxos[0]->outpoint->txid);
        self::assertSame(1, $utxos[0]->outpoint->vout);
        self::assertSame('Basic ' . base64_encode('rpc-user:rpc-password'), $http->request?->getHeaderLine('Authorization'));
    }

    public function testRpcErrorPreservesCode(): void
    {
        $client = $this->client($this->http('{"result":null,"error":{"code":-5,"message":"Transaction not found"},"id":"bitcoin-core"}'));

        try {
            $client->getTransaction('abc');
            self::fail('Expected BitcoinRpcException.');
        } catch (BitcoinRpcException $exception) {
            self::assertSame(-5, $exception->rpcCode);
            self::assertSame('Transaction not found', $exception->getMessage());
        }
    }

    public function testWalletInfoReadsBalancesFromGetBalances(): void
    {
        $client = $this->client($this->http([
            '{"result":{"walletname":"hot-wallet","walletversion":169900,"txcount":0,"private_keys_enabled":true,"descriptors":true,"unlocked_until":0},"error":null,"id":"bitcoin-core"}',
            '{"result":{"mine":{"trusted":0.05000001,"untrusted_pending":0.00000002,"immature":0.00000000}},"error":null,"id":"bitcoin-core"}',
        ]));

        $wallet = $client->getWalletInfo();

        self::assertSame('hot-wallet', $wallet->walletName);
        self::assertSame('5000001', $wallet->balance->satoshis);
        self::assertSame('2', $wallet->unconfirmedBalance->satoshis);
        self::assertSame(0, $wallet->unlockedUntil);
    }

    public function testGetAddressInfoAcceptsWalletResponseWithoutIsValid(): void
    {
        $client = $this->client($this->http('{"result":{"address":"bc1qexample","ismine":true,"iswatchonly":false,"scriptPubKey":"0014abcd","desc":"wpkh(example)#checksum"},"error":null,"id":"bitcoin-core"}'));

        $address = $client->getAddressInfo('bc1qexample');

        self::assertTrue($address->isValid);
        self::assertTrue($address->isMine);
        self::assertFalse($address->isWatchOnly);
        self::assertSame('wpkh(example)#checksum', $address->descriptor);
    }

    public function testValidateAddressStillRequiresIsValid(): void
    {
        $client = $this->client($this->http('{"result":{"isvalid":false},"error":null,"id":"bitcoin-core"}'));

        self::assertFalse($client->validateAddress('invalid')->isValid);
    }

    private function client(ClientInterface $http): BitcoinRpcClient
    {
        $factory = new Psr17Factory();

        return new BitcoinRpcClient(
            $http,
            $factory,
            $factory,
            'http://bitcoin-node:8332/wallet/test_wallet',
            'rpc-user',
            'rpc-password',
            new BitcoinJsonDecoder(),
        );
    }

    /**
     * @param string|list<string> $body
     */
    private function http(string|array $body): RecordingHttpClient
    {
        return new RecordingHttpClient($body);
    }
}
