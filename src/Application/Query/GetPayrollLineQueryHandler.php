<?php

declare(strict_types=1);

namespace Payroll\Application\Query;

use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Application\ReadModel\PayrollLineReadModelRepositoryInterface;

final class GetPayrollLineQueryHandler
{
    public function __construct(
        private readonly PayrollLineReadModelRepositoryInterface $readModelRepository,
    ) {
    }

    public function handle(GetPayrollLineQuery $query): ?PayrollLineReadModel
    {
        return $this->readModelRepository->find($query->employeeId);
    }
}
