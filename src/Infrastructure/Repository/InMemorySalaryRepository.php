<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Repository;

use Payroll\Domain\Salary\Aggregate\Salary;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\SalaryRepository;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;

final class InMemorySalaryRepository implements SalaryRepository
{
    public function __construct(
        private readonly InMemoryPayrollLineEventStore $eventStore,
    ) {
    }

    public function load(string $employeeId): Salary
    {
        return Salary::rehydrate($employeeId, $this->eventStore->getEventsFor($employeeId));
    }

    /** @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> events committed by this save */
    public function save(Salary $salary): array
    {
        $events = $salary->getUncommittedEvents();

        $this->eventStore->append($events);
        $salary->clearUncommittedEvents();

        return $events;
    }
}
