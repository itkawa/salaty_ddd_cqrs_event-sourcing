<?php

declare(strict_types=1);

namespace Payroll\Application\Query;

use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Application\ReadModel\PayrollLineReadModelRepository;

final class GetPayrollLineQueryHandler
{
    public function __construct(
        private readonly PayrollLineReadModelRepository $readModelRepository,
    ) {
    }

    public function handle(GetPayrollLineQuery $query): ?PayrollLineReadModel
    {
        return $this->readModelRepository->find($query->employeeId);
    }
}
