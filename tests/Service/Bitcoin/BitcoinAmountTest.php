<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Bitcoin;

use Amashukov\BlockchainContextBundle\Service\Bitcoin\BitcoinAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BitcoinAmountTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function decimalCases(): iterable
    {
        yield 'zero' => ['0', '0'];
        yield 'one satoshi' => ['0.00000001', '1'];
        yield 'one bitcoin' => ['1', '100000000'];
        yield 'fraction' => ['12.3456789', '1234567890'];
        yield 'negative fee' => ['-0.0000028', '-280'];
    }

    #[DataProvider('decimalCases')]
    public function testDecimalAndSatoshiConversionsAreExact(string $decimal, string $satoshis): void
    {
        $amount = BitcoinAmount::fromDecimal($decimal);

        self::assertSame($satoshis, $amount->satoshis);
        self::assertSame($amount->decimal(), BitcoinAmount::fromSatoshis($satoshis)->decimal());
    }

    public function testRejectsExcessPrecision(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BitcoinAmount::fromDecimal('0.000000001');
    }
}
