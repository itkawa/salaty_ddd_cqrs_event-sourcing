<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Domain\Salary\Aggregate;

use Payroll\Domain\Salary\Aggregate\Salary;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SalaryTest extends TestCase
{
    private const EMPLOYEE_ID = 'emp-001';

    public function testFreshSalaryStartsAtZeroWithNoUncommittedEvents(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);

        self::assertSame('0.00', $salary->getCurrentAmount()->value());
        self::assertSame([], $salary->getUncommittedEvents());
    }

    public function testApplyingAnAutomaticAmountUpdatesCurrentAmountAndRecordsAnEvent(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);

        $applied = $salary->applyAutoCalculatedAmount(new Money('1000.00'));

        self::assertTrue($applied);
        self::assertSame('1000.00', $salary->getCurrentAmount()->value());

        $events = $salary->getUncommittedEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AutoPayrollAmountChanged::class, $events[0]);
        self::assertSame('1000.00', $events[0]->baseAmount->value());
        self::assertSame(1, $events[0]->version);
    }

    public function testAutomaticRecalculationIsAllowedBeforeAnyManualAdjustment(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);

        $salary->applyAutoCalculatedAmount(new Money('1000.00'));
        $salary->applyAutoCalculatedAmount(new Money('1050.00'));

        self::assertSame('1050.00', $salary->getCurrentAmount()->value());
        self::assertCount(2, $salary->getUncommittedEvents());
    }

    public function testManualAdjustmentBeforeAnyAutomaticAmountIsRejected(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add a manual adjustment before an automatic amount has been applied.');

        $salary->addManualAdjustment(new Money('10.00'), 'too early');
    }

    public function testManualAdjustmentRequiresANonEmptyComment(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);
        $salary->applyAutoCalculatedAmount(new Money('1000.00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A comment is required for a manual adjustment.');

        $salary->addManualAdjustment(new Money('-10.00'), '   ');
    }

    public function testManualAdjustmentAppliesItsDeltaAndRecordsAnEvent(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);
        $salary->applyAutoCalculatedAmount(new Money('1050.00'));

        $salary->addManualAdjustment(new Money('-45.55'), 'Employee declined dental benefit; reversing deduction');

        self::assertSame('1004.45', $salary->getCurrentAmount()->value());

        $events = $salary->getUncommittedEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(ManualAdjustmentAdded::class, $events[1]);
        self::assertSame('-45.55', $events[1]->adjustment->value());
        self::assertSame('Employee declined dental benefit; reversing deduction', $events[1]->comment);
        self::assertSame(2, $events[1]->version);
    }

    public function testAutomaticRecalculationIsIgnoredOnceAManualAdjustmentExists(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);
        $salary->applyAutoCalculatedAmount(new Money('1050.00'));
        $salary->addManualAdjustment(new Money('-45.55'), 'reversing deduction');

        $eventCountBefore = count($salary->getUncommittedEvents());

        $applied = $salary->applyAutoCalculatedAmount(new Money('9999.99'));

        self::assertFalse($applied);
        self::assertSame('1004.45', $salary->getCurrentAmount()->value());
        self::assertCount($eventCountBefore, $salary->getUncommittedEvents());
    }

    public function testClearUncommittedEventsEmptiesTheCollectionWithoutAffectingState(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);
        $salary->applyAutoCalculatedAmount(new Money('1000.00'));

        $salary->clearUncommittedEvents();

        self::assertSame([], $salary->getUncommittedEvents());
        self::assertSame('1000.00', $salary->getCurrentAmount()->value());
    }

    public function testRehydrateRebuildsStateFromHistoricalEventsWithoutNewUncommittedEvents(): void
    {
        $events = [
            new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1000.00'), new \DateTimeImmutable(), 1),
            new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1050.00'), new \DateTimeImmutable(), 2),
            new ManualAdjustmentAdded(self::EMPLOYEE_ID, new Money('-45.55'), 'reversing deduction', new \DateTimeImmutable(), 3),
        ];

        $salary = Salary::rehydrate(self::EMPLOYEE_ID, $events);

        self::assertSame('1004.45', $salary->getCurrentAmount()->value());
        self::assertSame([], $salary->getUncommittedEvents());
    }

    public function testRehydrateEnforcesTheManualAfterAutoInvariantAndBlocksFurtherAutoChanges(): void
    {
        $events = [
            new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1000.00'), new \DateTimeImmutable(), 1),
            new ManualAdjustmentAdded(self::EMPLOYEE_ID, new Money('50.00'), 'correction', new \DateTimeImmutable(), 2),
        ];

        $salary = Salary::rehydrate(self::EMPLOYEE_ID, $events);
        $salary->applyAutoCalculatedAmount(new Money('9999.99'));

        self::assertSame('1050.00', $salary->getCurrentAmount()->value());
        self::assertSame([], $salary->getUncommittedEvents());
    }

    public function testRehydrateRejectsAGapInEventVersions(): void
    {
        $events = [
            new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1000.00'), new \DateTimeImmutable(), 1),
            new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1050.00'), new \DateTimeImmutable(), 3),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected event version');

        Salary::rehydrate(self::EMPLOYEE_ID, $events);
    }

    /**
     * Full scenario from the assignment's own worked example — replays all 8
     * steps directly against the aggregate and checks the current value after
     * every step matches the specification's table exactly.
     */
    public function testMatchesTheAssignmentWorkedExample(): void
    {
        $salary = Salary::forEmployee(self::EMPLOYEE_ID);

        $salary->applyAutoCalculatedAmount(new Money('1000.00'));
        self::assertSame('1000.00', $salary->getCurrentAmount()->value(), 'step 1');

        $salary->applyAutoCalculatedAmount(new Money('1050.00'));
        self::assertSame('1050.00', $salary->getCurrentAmount()->value(), 'step 2');

        $salary->addManualAdjustment(new Money('-45.55'), 'Employee declined dental benefit; reversing deduction');
        self::assertSame('1004.45', $salary->getCurrentAmount()->value(), 'step 3');

        $salary->applyAutoCalculatedAmount(new Money('9999.99')); // must be ignored
        self::assertSame('1004.45', $salary->getCurrentAmount()->value(), 'step 4');

        $salary->addManualAdjustment(new Money('100.10'), 'Late correction: missed approved overtime bonus');
        self::assertSame('1104.55', $salary->getCurrentAmount()->value(), 'step 5');

        $salary->addManualAdjustment(new Money('-0.10'), 'Minor rounding adjustment');
        self::assertSame('1104.45', $salary->getCurrentAmount()->value(), 'step 6');

        $salary->addManualAdjustment(new Money('-0.20'), 'Second minor rounding adjustment');
        self::assertSame('1104.25', $salary->getCurrentAmount()->value(), 'step 7');

        $salary->addManualAdjustment(new Money('0.20'), 'Correcting mistake in adjustment #4');
        self::assertSame('1104.45', $salary->getCurrentAmount()->value(), 'step 8');

        // 2 automatic + 5 manual = 7 uncommitted events; the ignored step 4 attempt created none.
        self::assertCount(7, $salary->getUncommittedEvents());
    }
}
