<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

interface ConfirmationCheckInterface
{
    public function supports(OrderView $order): bool;

    public function check(OrderView $order): ConfirmationState;
}
