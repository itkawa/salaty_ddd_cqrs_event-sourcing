<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Infrastructure\Repository\SalaryRepository;

final class ApplyAutoCalculatedAmountCommandHandler
{
    public function __construct(
        private readonly SalaryRepository $salaryRepository,
        private readonly PayrollLineProjectionHandler $projectionHandler,
    ) {
    }

    public function handle(ApplyAutoCalculatedAmountCommand $command): void
    {
        $salary = $this->salaryRepository->load($command->employeeId);

        $salary->applyAutoCalculatedAmount($command->baseAmount);

        foreach ($this->salaryRepository->save($salary) as $event) {
            $this->projectionHandler->handle($event);
        }
    }
}
