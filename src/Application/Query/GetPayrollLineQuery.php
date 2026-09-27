<?php

declare(strict_types=1);

namespace Payroll\Application\Query;

final class GetPayrollLineQuery
{
    public function __construct(
        public readonly string $employeeId,
    ) {
    }
}
