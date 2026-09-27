<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application;

use Payroll\Application\Command\AddManualAdjustmentCommand;
use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Handler\AddManualAdjustmentCommandHandler;
use Payroll\Application\Handler\ApplyAutoCalculatedAmountCommandHandler;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Application\Query\GetPayrollLineQuery;
use Payroll\Application\Query\GetPayrollLineQueryHandler;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use Payroll\Infrastructure\Logging\InMemoryRejectedRecalculationLog;
use Payroll\Infrastructure\Repository\InMemoryPayrollLineReadModelRepository;
use Payroll\Infrastructure\Repository\InMemorySalaryRepository;
use PHPUnit\Framework\TestCase;

/**
 * Replays the assignment's own worked example through the full write+read
 * pipeline (commands -> handlers -> projection -> read model -> query),
 * not just against the aggregate directly (see SalaryTest for that).
 * This is what actually proves "current value and full audit history"
 * (the assignment's own requirement) are visible through the read side.
 */
final class PayrollLineWorkedExampleFlowTest extends TestCase
{
    private const EMPLOYEE_ID = 'emp-001';

    public function testTheFullPipelineMatchesTheAssignmentsExpectedAuditHistory(): void
    {
        $eventStore = new InMemoryPayrollLineEventStore();
        $salaryRepository = new InMemorySalaryRepository($eventStore);
        $readModelRepository = new InMemoryPayrollLineReadModelRepository();
        $projectionHandler = new PayrollLineProjectionHandler($readModelRepository);
        $rejectedRecalculationLog = new InMemoryRejectedRecalculationLog();

        $autoHandler = new ApplyAutoCalculatedAmountCommandHandler($salaryRepository, $projectionHandler, $rejectedRecalculationLog);
        $manualHandler = new AddManualAdjustmentCommandHandler($salaryRepository, $projectionHandler);
        $queryHandler = new GetPayrollLineQueryHandler($readModelRepository);

        $autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('1000.00')));
        $autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('1050.00')));
        $manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('-45.55'), 'Employee declined dental benefit; reversing deduction'));
        $autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('9999.99'))); // must be ignored
        $manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('100.10'), 'Late correction: missed approved overtime bonus'));
        $manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('-0.10'), 'Minor rounding adjustment'));
        $manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('-0.20'), 'Second minor rounding adjustment'));
        $manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('0.20'), 'Correcting mistake in adjustment #4'));

        $line = $queryHandler->handle(new GetPayrollLineQuery(self::EMPLOYEE_ID));

        self::assertNotNull($line);
        self::assertSame('1050.00', $line->baseAmount()->value(), 'system value frozen at step 3');
        self::assertSame('1104.45', $line->currentAmount()->value(), 'current (new) value');

        $adjustments = $line->manualAdjustments();
        self::assertCount(5, $adjustments);
        self::assertSame('-45.55', $adjustments[0]->adjustment->value());
        self::assertSame('100.10', $adjustments[1]->adjustment->value());
        self::assertSame('-0.10', $adjustments[2]->adjustment->value());
        self::assertSame('-0.20', $adjustments[3]->adjustment->value());
        self::assertSame('0.20', $adjustments[4]->adjustment->value());

        // The rejected step-4 attempt is logged, not part of the audit trail.
        self::assertCount(1, $rejectedRecalculationLog->allFor(self::EMPLOYEE_ID));

        // Event Sourcing check: rehydrating Salary independently matches the read side.
        $rehydrated = $salaryRepository->load(self::EMPLOYEE_ID);
        self::assertSame($line->currentAmount()->value(), $rehydrated->getCurrentAmount()->value());
    }
}
