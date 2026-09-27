<?php

declare(strict_types=1);

namespace Payroll\Application\Command;

use Payroll\Domain\Salary\ValueObject\Money;

final class ApplyAutoCalculatedAmountCommand
{
    public function __construct(
        public readonly string $employeeId,
        public readonly Money $baseAmount,
    ) {
    }
}
