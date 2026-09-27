<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Repository;

use Payroll\Domain\Salary\Aggregate\Salary;

interface SalaryRepositoryInterface
{
    public function load(string $employeeId): Salary;

    /** @throws ConcurrencyException if another save already advanced this employee's version */
    public function save(Salary $salary): void;
}
