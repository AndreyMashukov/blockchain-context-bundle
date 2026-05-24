<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Time;

use Amashukov\BlockchainContextBundle\Service\Time\RealSleeper;
use Amashukov\BlockchainContextBundle\Service\Time\SleeperInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RealSleeper::class)]
final class RealSleeperTest extends TestCase
{
    public function testImplementsSleeperInterface(): void
    {
        self::assertInstanceOf(SleeperInterface::class, new RealSleeper());
    }

    public function testSleepZeroSecondsReturnsImmediately(): void
    {
        $sleeper = new RealSleeper();
        $start   = microtime(true);

        $sleeper->sleep(0);

        self::assertLessThan(0.05, microtime(true) - $start, 'sleep(0) must return immediately');
    }
}
