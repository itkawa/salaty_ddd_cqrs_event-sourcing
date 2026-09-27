<?php

declare(strict_types=1);

namespace Payroll\Application\Projection;

use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Application\ReadModel\PayrollLineReadModelRepositoryInterface;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;

final class PayrollLineProjectionHandler
{
    public function __construct(
        private readonly PayrollLineReadModelRepositoryInterface $readModelRepository,
    ) {
    }

    public function handle(AutoPayrollAmountChanged|ManualAdjustmentAdded $event): void
    {
        $line = $this->readModelRepository->find($event->employeeId);

        if ($event instanceof AutoPayrollAmountChanged) {
            if ($line === null) {
                $line = PayrollLineReadModel::initialize($event->employeeId, $event->baseAmount, $event->createdAt);
            } else {
                $line->recordAutomaticRecalculation($event->baseAmount, $event->createdAt);
            }
        } else {
            if ($line === null) {
                throw new \LogicException('Cannot record a manual adjustment before the payroll line exists.');
            }

            $line->recordManualAdjustment($event->adjustment, $event->comment, $event->createdAt);
        }

        $this->readModelRepository->save($line);
    }
}
