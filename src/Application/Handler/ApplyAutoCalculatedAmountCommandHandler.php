<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Logging\RejectedAutoRecalculation;
use Payroll\Application\Logging\RejectedRecalculationLogInterface;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Domain\Salary\Repository\SalaryRepositoryInterface;

final class ApplyAutoCalculatedAmountCommandHandler
{
    public function __construct(
        private readonly SalaryRepositoryInterface $salaryRepository,
        private readonly PayrollLineProjectionHandler $projectionHandler,
        private readonly RejectedRecalculationLogInterface $rejectedRecalculationLog,
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
