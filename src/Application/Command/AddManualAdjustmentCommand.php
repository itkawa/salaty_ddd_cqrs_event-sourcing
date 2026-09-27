<?php

declare(strict_types=1);

namespace Payroll\Application\Command;

use Payroll\Domain\Salary\ValueObject\Money;

final class AddManualAdjustmentCommand
{
    public function __construct(
        public readonly string $employeeId,
        public readonly Money $adjustment,
        public readonly string $comment,
    ) {
    }
}
