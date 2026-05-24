<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Tests\Service\Finality;

use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCounterInterface;
use Amashukov\BlockchainContextBundle\Service\Finality\ConfirmationCounterRegistry;
use Amashukov\BlockchainContextBundle\Service\Finality\DepositTxView;
use Amashukov\BlockchainContextBundle\Service\Finality\OrderView;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfirmationCounterRegistry::class)]
final class ConfirmationCounterRegistryTest extends TestCase
{
    public function testForChainReturnsFirstMatch(): void
    {
        $eth = $this->counter(supports: ['eth']);
        $ton = $this->counter(supports: ['ton']);

        $registry = new ConfirmationCounterRegistry([$eth, $ton]);
        self::assertSame($ton, $registry->forChain('ton'));
        self::assertSame($eth, $registry->forChain('eth'));
    }

    public function testForChainThrowsWhenNoMatch(): void
    {
        $registry = new ConfirmationCounterRegistry([$this->counter(supports: ['eth'])]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No ConfirmationCounter registered for chain "stars"');
        $registry->forChain('stars');
    }

    public function testForChainThrowsOnEmptyRegistry(): void
    {
        $registry = new ConfirmationCounterRegistry([]);

        $this->expectException(LogicException::class);
        $registry->forChain('ton');
    }

    /**
     * @param list<string> $supports
     */
    private function counter(array $supports): ConfirmationCounterInterface
    {
        return new readonly class ($supports) implements ConfirmationCounterInterface {
            /**
             * @param list<string> $supports
             */
            public function __construct(private array $supports) {}

            public function supports(string $chain): bool
            {
                return in_array($chain, $this->supports, true);
            }

            public function count(DepositTxView $depositTx, OrderView $order): int
            {
                return 0;
            }
        };
    }
}
