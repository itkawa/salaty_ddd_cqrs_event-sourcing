<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Repository;

use Payroll\Domain\Salary\Aggregate\Salary;
use Payroll\Domain\Salary\Repository\SalaryRepositoryInterface;
use Payroll\Infrastructure\EventStore\PayrollLineEventStoreInterface;

final class InMemorySalaryRepository implements SalaryRepositoryInterface
{
    public function __construct(
        private readonly PayrollLineEventStoreInterface $eventStore,
    ) {
    }

    public function load(string $employeeId): Salary
    {
        return Salary::rehydrate($employeeId, $this->eventStore->getEventsFor($employeeId));
    }

    public function save(Salary $salary): void
    {
        $this->eventStore->append($salary->getUncommittedEvents());
        $salary->clearUncommittedEvents();
    }
}
