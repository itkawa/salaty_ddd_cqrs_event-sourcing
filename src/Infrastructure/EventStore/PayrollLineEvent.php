<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\EventStore;

use Payroll\Domain\Salary\ValueObject\Money;

/**
 * Uniform storage record — every row has the same shape, matching a future
 * payroll_events table. baseAmount/adjustment/comment are nullable because
 * only one of baseAmount/adjustment is set depending on eventType, and
 * comment only applies to ManualAdjustmentAdded.
 */
final class PayrollLineEvent
{
    public function __construct(
        public readonly int $id,
        public readonly string $employeeId,
        public readonly string $eventType,
        public readonly ?Money $baseAmount,
        public readonly ?Money $adjustment,
        public readonly ?string $comment,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $version,
    ) {
    }
}
