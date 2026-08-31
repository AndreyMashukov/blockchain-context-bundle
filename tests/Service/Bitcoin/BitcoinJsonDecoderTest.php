<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinJsonDecoder;
use PHPUnit\Framework\TestCase;

final class BitcoinJsonDecoderTest extends TestCase
{
    public function testPreservesEveryNumberLexemeAsAString(): void
    {
        $decoded = (new BitcoinJsonDecoder())->decode('{"result":{"amount":0.12345678,"height":912345,"negative":-0.00000280,"label":"v1.2"},"error":null,"id":1}');
        $result  = $decoded['result'];
        self::assertIsArray($result);

        self::assertSame('0.12345678', $result['amount']);
        self::assertSame('912345', $result['height']);
        self::assertSame('-0.00000280', $result['negative']);
        self::assertSame('v1.2', $result['label']);
        self::assertSame('1', $decoded['id']);
    }
}
