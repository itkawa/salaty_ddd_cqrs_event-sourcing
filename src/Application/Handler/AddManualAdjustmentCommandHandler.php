<?php

declare(strict_types=1);

namespace Payroll\Application\Handler;

use Payroll\Application\Command\AddManualAdjustmentCommand;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Domain\Salary\Repository\SalaryRepository;

final class AddManualAdjustmentCommandHandler
{
    public function __construct(
        private readonly SalaryRepository $salaryRepository,
        private readonly PayrollLineProjectionHandler $projectionHandler,
    ) {
    }

    public function handle(AddManualAdjustmentCommand $command): void
    {
        $salary = $this->salaryRepository->load($command->employeeId);

        $salary->addManualAdjustment($command->adjustment, $command->comment);

        foreach ($this->salaryRepository->save($salary) as $event) {
            $this->projectionHandler->handle($event);
        }
    }
}
