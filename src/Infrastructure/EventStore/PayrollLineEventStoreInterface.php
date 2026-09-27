<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\EventStore;

use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\Repository\ConcurrencyException;

interface PayrollLineEventStoreInterface
{
    /**
     * @param array<AutoPayrollAmountChanged|ManualAdjustmentAdded> $events
     *
     * @throws ConcurrencyException if any event's version doesn't match what's
     *         expected for its employeeId.
     */
    public function append(array $events): void;

    /** @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> */
    public function getEventsFor(string $employeeId): array;
}
