<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfirmationState::class)]
final class ConfirmationStateTest extends TestCase
{
    public function testEnumCasesAreDistinct(): void
    {
        $cases = ConfirmationState::cases();
        self::assertCount(4, $cases);

        $names = array_map(static fn(ConfirmationState $c) => $c->name, $cases);
        self::assertSame(['CONFIRMED', 'REVERTED', 'PENDING', 'REORG_ORPHAN'], $names);
    }

    public function testNoDuplicateCases(): void
    {
        self::assertNotSame(ConfirmationState::CONFIRMED, ConfirmationState::REVERTED);
        self::assertNotSame(ConfirmationState::PENDING, ConfirmationState::CONFIRMED);
        self::assertNotSame(ConfirmationState::PENDING, ConfirmationState::REVERTED);
        self::assertNotSame(ConfirmationState::REORG_ORPHAN, ConfirmationState::CONFIRMED);
        self::assertNotSame(ConfirmationState::REORG_ORPHAN, ConfirmationState::REVERTED);
        self::assertNotSame(ConfirmationState::REORG_ORPHAN, ConfirmationState::PENDING);
    }
}
