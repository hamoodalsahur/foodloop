<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when code tries to break one of the business rules in
 * docs/BUILD_PLAN.md §3. Forms should validate first and show a friendly
 * message; this is the safety net underneath them.
 */
class BusinessRuleViolation extends DomainException
{
    public static function certifiedNeedsCertificate(): self
    {
        return new self('A listing can only say "halal certified" when the seller has an approved halal certificate on file.');
    }

    public static function sellerNotApproved(): self
    {
        return new self('A listing can only go live after an admin has approved the seller.');
    }
}
