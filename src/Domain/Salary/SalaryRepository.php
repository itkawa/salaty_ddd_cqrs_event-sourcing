<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary;

use Payroll\Domain\Salary\Aggregate\Salary;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;

interface SalaryRepository
{
    public function load(string $employeeId): Salary;

    /** @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> events committed by this save */
    public function save(Salary $salary): array;
}
