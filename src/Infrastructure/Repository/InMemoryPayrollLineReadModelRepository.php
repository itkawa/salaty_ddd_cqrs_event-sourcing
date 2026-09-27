<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Repository;

use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Application\ReadModel\PayrollLineReadModelRepositoryInterface;

final class InMemoryPayrollLineReadModelRepository implements PayrollLineReadModelRepositoryInterface
{
    /** @var array<string, PayrollLineReadModel> */
    private array $lines = [];

    public function find(string $employeeId): ?PayrollLineReadModel
    {
        return $this->lines[$employeeId] ?? null;
    }

    public function save(PayrollLineReadModel $line): void
    {
        $this->lines[$line->employeeId()] = $line;
    }
}
