<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

use LogicException;

final readonly class ConfirmationCounterRegistry
{
    /**
     * @param iterable<ConfirmationCounterInterface> $counters
     */
    public function __construct(
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
