<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\NoopFinalityVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoopFinalityVerifier::class)]
final class NoopFinalityVerifierTest extends TestCase
{
    public function testVerifyDepthAlwaysReturnsTrue(): void
    {
        $verifier = new NoopFinalityVerifier();

        self::assertTrue($verifier->verifyDepth('0:abc', 'someHash=', 0.0));
        self::assertTrue($verifier->verifyDepth('', '', PHP_FLOAT_MAX));
    }

    public function testCheckPresenceAlwaysReturnsTrue(): void
    {
        $verifier = new NoopFinalityVerifier();

        self::assertTrue($verifier->checkPresence('0:abc', 'someHash='));
        self::assertTrue($verifier->checkPresence('', ''));
    }
}
