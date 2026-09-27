<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Logging\RejectedAutoRecalculation;
use Payroll\Application\Logging\RejectedRecalculationLog;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Domain\Salary\Repository\SalaryRepository;

final class ApplyAutoCalculatedAmountCommandHandler
{
    public function __construct(
        private readonly SalaryRepository $salaryRepository,
        private readonly PayrollLineProjectionHandler $projectionHandler,
        private readonly RejectedRecalculationLog $rejectedRecalculationLog,
    ) {
    }

    public function handle(ApplyAutoCalculatedAmountCommand $command): void
    {
        $salary = $this->salaryRepository->load($command->employeeId);

        $applied = $salary->applyAutoCalculatedAmount($command->baseAmount);

        if (!$applied) {
            $this->rejectedRecalculationLog->record(new RejectedAutoRecalculation(
                $command->employeeId,
                $command->baseAmount,
                new \DateTimeImmutable(),
            ));
        }

        foreach ($this->salaryRepository->save($salary) as $event) {
            $this->projectionHandler->handle($event);
        }
    }
}
