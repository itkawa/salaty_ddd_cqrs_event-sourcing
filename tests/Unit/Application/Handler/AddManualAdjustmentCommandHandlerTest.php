<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Handler;

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

final class AddManualAdjustmentCommandHandlerTest extends TestCase
{
    private const EMPLOYEE_ID = 'emp-001';

    private AddManualAdjustmentCommandHandler $manualHandler;
    private ApplyAutoCalculatedAmountCommandHandler $autoHandler;
    private GetPayrollLineQueryHandler $queryHandler;

    protected function setUp(): void
    {
        $salaryRepository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());
        $readModelRepository = new InMemoryPayrollLineReadModelRepository();
        $projectionHandler = new PayrollLineProjectionHandler($readModelRepository);

        $this->autoHandler = new ApplyAutoCalculatedAmountCommandHandler(
            $salaryRepository,
            $projectionHandler,
            new InMemoryRejectedRecalculationLog(),
        );
        $this->manualHandler = new AddManualAdjustmentCommandHandler($salaryRepository, $projectionHandler);
        $this->queryHandler = new GetPayrollLineQueryHandler($readModelRepository);
    }

    public function testAManualAdjustmentUpdatesTheReadModel(): void
    {
        $this->autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('1050.00')));
        $this->manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('-45.55'), 'reversing deduction'));

        $line = $this->queryHandler->handle(new GetPayrollLineQuery(self::EMPLOYEE_ID));

        self::assertSame('1004.45', $line->currentAmount()->value());
        self::assertCount(1, $line->manualAdjustments());
    }

    public function testAManualAdjustmentBeforeAnyAutomaticAmountIsRejectedAndLeavesNoReadModel(): void
    {
        try {
            $this->manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('10.00'), 'too early'));
            self::fail('Expected a LogicException.');
        } catch (\LogicException) {
            // expected
        }

        self::assertNull($this->queryHandler->handle(new GetPayrollLineQuery(self::EMPLOYEE_ID)));
    }
}
