<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Event;

use Payroll\Domain\Salary\ValueObject\Money;

final class AutoPayrollAmountChanged
{
    public function __construct(
        public readonly string $employeeId,
        public readonly Money $baseAmount,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $version,
    ) {
    }
}
