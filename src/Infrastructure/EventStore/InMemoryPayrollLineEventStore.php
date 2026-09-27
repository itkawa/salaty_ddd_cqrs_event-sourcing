<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\EventStore;

use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;

/**
 * Simulates one payroll_events table: every row is a uniform PayrollLineEvent
 * record, regardless of which domain event produced it. Callers (SalaryRepository)
 * only ever see domain events (AutoPayrollAmountChanged|ManualAdjustmentAdded) —
 * translation to/from the storage record shape is entirely internal here.
 */
final class InMemoryPayrollLineEventStore
{
    /** @var PayrollLineEvent[] */
    private array $payrollLineEvents = [];

    private int $nextId = 1;

    /** @param array<AutoPayrollAmountChanged|ManualAdjustmentAdded> $events */
    public function append(array $events): void
    {
        foreach ($events as $event) {
            $this->payrollLineEvents[] = $this->toStorageRecord($event);
        }
    }

    /** @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> */
    public function getEventsFor(string $employeeId): array
    {
        $records = array_values(array_filter(
            $this->payrollLineEvents,
            static fn (PayrollLineEvent $record): bool => $record->employeeId === $employeeId,
        ));

        return array_map($this->toDomainEvent(...), $records);
    }

    private function toStorageRecord(AutoPayrollAmountChanged|ManualAdjustmentAdded $event): PayrollLineEvent
    {
        $id = $this->nextId++;

        return match (true) {
            $event instanceof AutoPayrollAmountChanged => new PayrollLineEvent(
                id: $id,
                employeeId: $event->employeeId,
                eventType: 'AutoPayrollAmountChanged',
                baseAmount: $event->baseAmount,
                adjustment: null,
                comment: null,
                createdAt: $event->createdAt,
                version: $event->version,
            ),
            $event instanceof ManualAdjustmentAdded => new PayrollLineEvent(
                id: $id,
                employeeId: $event->employeeId,
                eventType: 'ManualAdjustmentAdded',
                baseAmount: null,
                adjustment: $event->adjustment,
                comment: $event->comment,
                createdAt: $event->createdAt,
                version: $event->version,
            ),
        };
    }

    private function toDomainEvent(PayrollLineEvent $record): AutoPayrollAmountChanged|ManualAdjustmentAdded
    {
        return match ($record->eventType) {
            'AutoPayrollAmountChanged' => new AutoPayrollAmountChanged(
                employeeId: $record->employeeId,
                baseAmount: $record->baseAmount,
                createdAt: $record->createdAt,
                version: $record->version,
            ),
            'ManualAdjustmentAdded' => new ManualAdjustmentAdded(
                employeeId: $record->employeeId,
                adjustment: $record->adjustment,
                comment: $record->comment,
                createdAt: $record->createdAt,
                version: $record->version,
            ),
        };
    }
}
