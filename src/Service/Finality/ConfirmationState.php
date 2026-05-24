<?php

declare(strict_types=1);

namespace Amashukov\BlockchainContextBundle\Service\Finality;

enum ConfirmationState
{
    case CONFIRMED;
    case REVERTED;
    case PENDING;
    case REORG_ORPHAN;
}
