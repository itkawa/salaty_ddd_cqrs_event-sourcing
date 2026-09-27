<?php

declare(strict_types=1);

namespace Payroll\Application\Logging;

use Payroll\Domain\Salary\ValueObject\Money;

/**
 * A technical-log entry for an automatic recalculation attempt that was
 * rejected because a manual adjustment already exists. Not a domain event —
 * it never enters the Event Store, since it represents no state change.
 */
final class RejectedAutoRecalculation
{
    public function __construct(
        public readonly string $employeeId,
        public readonly Money $attemptedAmount,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }
}
