<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ConfirmationCounterRegistry
{
    /**
     * @param iterable<ConfirmationCounterInterface> $counters
     */
    public function __construct(
        #[AutowireIterator('blockchain_context.confirmation_counter')]
        private iterable $counters,
    ) {}

    public function forChain(string $chain): ConfirmationCounterInterface
    {
        foreach ($this->counters as $counter) {
            if ($counter->supports($chain)) {
                return $counter;
            }
        }

        throw new LogicException(sprintf('No ConfirmationCounter registered for chain "%s"', $chain));
    }
}
