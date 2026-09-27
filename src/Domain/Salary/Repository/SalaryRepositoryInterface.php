<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Repository;

use Payroll\Domain\Salary\Aggregate\Salary;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;

interface SalaryRepositoryInterface
{
    public function load(string $employeeId): Salary;

    /**
     * @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> events committed by this save
     *
     * @throws ConcurrencyException if another save already advanced this employee's version
     */
    public function save(Salary $salary): array;
}
