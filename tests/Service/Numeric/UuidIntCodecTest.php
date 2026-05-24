<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Numeric;

use Amashukov\BlockchainContextBundle\Service\Numeric\UuidIntCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UuidIntCodec::class)]
final class UuidIntCodecTest extends TestCase
{
    public function testEncodeStripsDashesAndProducesDecimalString(): void
    {
        $codec = new UuidIntCodec();
        self::assertSame(
            '340282366920938463463374607431768211455',
            $codec->encode('ffffffff-ffff-ffff-ffff-ffffffffffff'),
        );
    }

    public function testEncodeIsCaseInsensitive(): void
    {
        $codec = new UuidIntCodec();
        self::assertSame(
            $codec->encode('F8A3B2C1-4D5E-6789-ABCD-EF0123456789'),
            $codec->encode('f8a3b2c1-4d5e-6789-abcd-ef0123456789'),
        );
    }

    public function testEncodeRejectsMalformedUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('malformed UUID');

        (new UuidIntCodec())->encode('not-a-uuid');
    }

    public function testEncodeRejectsTruncatedUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UuidIntCodec())->encode('f8a3b2c1-4d5e-6789-abcd-ef012345');
    }

    public function testEncodeRejectsExtraCharsUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UuidIntCodec())->encode('f8a3b2c1-4d5e-6789-abcd-ef0123456789zz');
    }

    public function testDecodeRoundTripsToCanonicalUuid(): void
    {
        $codec    = new UuidIntCodec();
        $original = 'f8a3b2c1-4d5e-6789-abcd-ef0123456789';

        self::assertSame($original, $codec->decode($codec->encode($original)));
    }

    public function testDecodeLeftPadsLowValueIntoUuidShape(): void
    {
        self::assertSame(
            '00000000-0000-0000-0000-000000000001',
            (new UuidIntCodec())->decode('1'),
        );
    }

    public function testDecodeHandlesMaxUuid(): void
    {
        $codec   = new UuidIntCodec();
        $maxUuid = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

        self::assertSame($maxUuid, $codec->decode($codec->encode($maxUuid)));
    }

    public function testDecodeRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UuidIntCodec())->decode('');
    }

    public function testDecodeRejectsNonNumericInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-numeric input');
        (new UuidIntCodec())->decode('123abc');
    }

    public function testDecodeRejectsValueExceeding128Bits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds 128 bits');
        (new UuidIntCodec())->decode('340282366920938463463374607431768211456');
    }

    public function testEncodeProducesDeterministicOutput(): void
    {
        $codec = new UuidIntCodec();
        $uuid  = 'a1b2c3d4-e5f6-7890-1234-567890abcdef';

        self::assertSame($codec->encode($uuid), $codec->encode($uuid));
    }
}
