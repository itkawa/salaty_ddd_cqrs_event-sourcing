<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Handler;

use Payroll\Application\Command\AddManualAdjustmentCommand;
use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Handler\AddManualAdjustmentCommandHandler;
use Payroll\Application\Handler\ApplyAutoCalculatedAmountCommandHandler;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use Payroll\Infrastructure\Logging\InMemoryRejectedRecalculationLog;
use Payroll\Infrastructure\Repository\InMemoryPayrollLineReadModelRepository;
use Payroll\Infrastructure\Repository\InMemorySalaryRepository;
use PHPUnit\Framework\TestCase;

final class ApplyAutoCalculatedAmountCommandHandlerTest extends TestCase
{
    private const EMPLOYEE_ID = 'emp-001';

    private ApplyAutoCalculatedAmountCommandHandler $autoHandler;
    private AddManualAdjustmentCommandHandler $manualHandler;
    private InMemoryRejectedRecalculationLog $rejectedRecalculationLog;

    protected function setUp(): void
    {
        $salaryRepository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());
        $projectionHandler = new PayrollLineProjectionHandler(new InMemoryPayrollLineReadModelRepository());
        $this->rejectedRecalculationLog = new InMemoryRejectedRecalculationLog();

        $this->autoHandler = new ApplyAutoCalculatedAmountCommandHandler(
            $salaryRepository,
            $projectionHandler,
            $this->rejectedRecalculationLog,
        );
        $this->manualHandler = new AddManualAdjustmentCommandHandler($salaryRepository, $projectionHandler);
    }

    public function testAnAcceptedRecalculationIsNotLogged(): void
    {
        $this->autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('1000.00')));

        self::assertSame([], $this->rejectedRecalculationLog->allFor(self::EMPLOYEE_ID));
    }

    public function testARejectedRecalculationIsRecordedInTheTechnicalLogNotTheEventStore(): void
    {
        $this->autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('1000.00')));
        $this->manualHandler->handle(new AddManualAdjustmentCommand(self::EMPLOYEE_ID, new Money('-45.55'), 'reversing deduction'));

        $this->autoHandler->handle(new ApplyAutoCalculatedAmountCommand(self::EMPLOYEE_ID, new Money('9999.99')));

        $entries = $this->rejectedRecalculationLog->allFor(self::EMPLOYEE_ID);
        self::assertCount(1, $entries);
        self::assertSame('9999.99', $entries[0]->attemptedAmount->value());
    }
}
