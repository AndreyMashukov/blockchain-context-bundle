<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Numeric;

use Amashukov\BlockchainContextBundle\Service\Numeric\BcDecimal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BcDecimal::class)]
class BcDecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function trimCases(): iterable
    {
        yield 'scale-18 zero pad'           => ['1.500000000000000000', '1.5'];
        yield 'integer pad'                 => ['40.000000000000000000', '40'];
        yield 'integer string passes thru'  => ['40', '40'];
        yield 'zero integer'                => ['0', '0'];
        yield 'zero scale-18'               => ['0.000000000000000000', '0'];
        yield 'empty becomes zero'          => ['', '0'];
        yield 'mid-precision'               => ['12.34500000', '12.345'];
        yield 'no dot, big integer'         => ['10000', '10000'];
        yield 'sub-wei'                     => ['0.000000000000000001', '0.000000000000000001'];
        yield 'zero with trailing dot'      => ['1.', '1'];

        yield 'negative scale-18 padded'    => ['-1.500000000000000000', '-1.5'];
        yield 'negative integer pad'        => ['-40.000000000000000000', '-40'];
        yield 'negative integer no dot'     => ['-40', '-40'];
        yield 'negative sub-wei'            => ['-0.000000000000000001', '-0.000000000000000001'];
    }

    #[DataProvider('trimCases')]
    public function testTrim(string $input, string $expected): void
    {
        self::assertSame($expected, BcDecimal::trim($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'plain decimal passes thru'   => ['1.5', '1.5'];
        yield 'integer passes thru'         => ['40', '40'];
        yield 'empty becomes zero'          => ['', '0'];
        yield 'sci-notation positive'       => ['1.0E-7', '0.0000001'];
        yield 'sci-notation lower-case e'   => ['2.5e-3', '0.0025'];
        yield 'sci-notation upper E'        => ['3.5E2', '350'];
    }

    #[DataProvider('normalizeCases')]
    public function testNormalize(string $input, string $expected): void
    {
        self::assertSame($expected, BcDecimal::normalize($input));
    }
}
