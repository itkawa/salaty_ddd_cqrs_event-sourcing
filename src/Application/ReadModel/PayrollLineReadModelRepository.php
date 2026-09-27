<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

interface PayrollLineReadModelRepository
{
    public function find(string $employeeId): ?PayrollLineReadModel;

    public function save(PayrollLineReadModel $line): void;
}
