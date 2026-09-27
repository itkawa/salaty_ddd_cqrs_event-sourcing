<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Event;

use Payroll\Domain\Salary\ValueObject\Money;

final class ManualAdjustmentAdded
{
    public function __construct(
        public readonly string $employeeId,
        public readonly Money $adjustment,
        public readonly string $comment,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $version,
    ) {
    }
}
