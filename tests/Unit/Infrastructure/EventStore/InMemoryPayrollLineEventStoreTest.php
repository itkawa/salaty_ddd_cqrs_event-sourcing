<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\EventStore;

use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use PHPUnit\Framework\TestCase;

final class InMemoryPayrollLineEventStoreTest extends TestCase
{
    public function testGetEventsForReturnsNothingForAnUnknownEmployee(): void
    {
        $store = new InMemoryPayrollLineEventStore();

        self::assertSame([], $store->getEventsFor('unknown'));
    }

    public function testRoundTripsAnAutoPayrollAmountChangedEvent(): void
    {
        $store = new InMemoryPayrollLineEventStore();
        $createdAt = new \DateTimeImmutable();

        $store->append([new AutoPayrollAmountChanged('emp-001', new Money('1000.00'), $createdAt, 1)]);

        $events = $store->getEventsFor('emp-001');
        self::assertCount(1, $events);
        self::assertInstanceOf(AutoPayrollAmountChanged::class, $events[0]);
        self::assertSame('1000.00', $events[0]->baseAmount->value());
        self::assertSame(1, $events[0]->version);
    }

    public function testRoundTripsAManualAdjustmentAddedEvent(): void
    {
        $store = new InMemoryPayrollLineEventStore();
        $createdAt = new \DateTimeImmutable();

        $store->append([new ManualAdjustmentAdded('emp-001', new Money('-45.55'), 'reversing deduction', $createdAt, 1)]);

        $events = $store->getEventsFor('emp-001');
        self::assertCount(1, $events);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[0]);
        self::assertSame('-45.55', $events[0]->adjustment->value());
        self::assertSame('reversing deduction', $events[0]->comment);
    }

    public function testGetEventsForFiltersByEmployeeIdAndPreservesOrder(): void
    {
        $store = new InMemoryPayrollLineEventStore();

        $store->append([new AutoPayrollAmountChanged('emp-001', new Money('1000.00'), new \DateTimeImmutable(), 1)]);
        $store->append([new AutoPayrollAmountChanged('emp-002', new Money('500.00'), new \DateTimeImmutable(), 1)]);
        $store->append([new ManualAdjustmentAdded('emp-001', new Money('10.00'), 'note', new \DateTimeImmutable(), 2)]);

        $events = $store->getEventsFor('emp-001');
        self::assertCount(2, $events);
        self::assertInstanceOf(AutoPayrollAmountChanged::class, $events[0]);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[1]);
    }
}
