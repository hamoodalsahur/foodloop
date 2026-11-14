<?php

namespace App\Exceptions;

use App\Enums\RefusalReason;

/**
 * The reservation engine said no. `$reason` tells the caller why, and its
 * label is a sentence that can be shown to the person as it is.
 */
class ReservationRefused extends BusinessRuleViolation
{
    public function __construct(public readonly RefusalReason $reason)
    {
        parent::__construct($reason->label());
    }
}
