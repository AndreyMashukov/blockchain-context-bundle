<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\TxBuilder;

use Amashukov\BlockchainContextBundle\Service\TxBuilder\DepositTxPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DepositTxPayload::class)]
final class DepositTxPayloadTest extends TestCase
{
    public function testConstructorStoresKindAndPayloadVerbatim(): void
    {
        $payload = new DepositTxPayload('ton-native', [
            'address' => 'UQ123',
            'amount'  => '1000000000',
            'payload' => 'base64body==',
        ]);

        self::assertSame('ton-native', $payload->kind);
        self::assertSame('UQ123', $payload->payload['address']);
        self::assertSame('1000000000', $payload->payload['amount']);
        self::assertSame('base64body==', $payload->payload['payload']);
    }

    public function testEachKindRoundTrips(): void
    {
        foreach (['ton-native', 'ton-jetton', 'evm-native', 'evm-erc20'] as $kind) {
            $vo = new DepositTxPayload($kind, ['marker' => $kind]);
            self::assertSame($kind, $vo->kind);
        }
    }
}
