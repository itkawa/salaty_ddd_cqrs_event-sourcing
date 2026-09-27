<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Projection;

use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\Repository\InMemoryPayrollLineReadModelRepository;
use PHPUnit\Framework\TestCase;

final class PayrollLineProjectionHandlerTest extends TestCase
{
    private const EMPLOYEE_ID = 'emp-001';

    public function testAutoEventCreatesTheReadModelWhenNoneExistsYet(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();
        $handler = new PayrollLineProjectionHandler($repository);

        $handler->handle(new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1000.00'), new \DateTimeImmutable(), 1));

        $line = $repository->find(self::EMPLOYEE_ID);
        self::assertNotNull($line);
        self::assertSame('1000.00', $line->baseAmount()->value());
        self::assertSame('1000.00', $line->currentAmount()->value());
        self::assertSame([], $line->manualAdjustments());
    }

    public function testSecondAutoEventUpdatesTheExistingReadModel(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();
        $handler = new PayrollLineProjectionHandler($repository);

        $handler->handle(new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1000.00'), new \DateTimeImmutable(), 1));
        $handler->handle(new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1050.00'), new \DateTimeImmutable(), 2));

        $line = $repository->find(self::EMPLOYEE_ID);
        self::assertSame('1050.00', $line->baseAmount()->value());
        self::assertSame('1050.00', $line->currentAmount()->value());
    }

    public function testManualEventAppendsAnAdjustmentAndUpdatesCurrentAmount(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();
        $handler = new PayrollLineProjectionHandler($repository);

        $handler->handle(new AutoPayrollAmountChanged(self::EMPLOYEE_ID, new Money('1050.00'), new \DateTimeImmutable(), 1));
        $handler->handle(new ManualAdjustmentAdded(self::EMPLOYEE_ID, new Money('-45.55'), 'reversing deduction', new \DateTimeImmutable(), 2));

        $line = $repository->find(self::EMPLOYEE_ID);
        self::assertSame('1004.45', $line->currentAmount()->value());
        self::assertCount(1, $line->manualAdjustments());
        self::assertSame('-45.55', $line->manualAdjustments()[0]->adjustment->value());
        self::assertSame('reversing deduction', $line->manualAdjustments()[0]->comment);
    }

    public function testManualEventBeforeAnyReadModelExistsThrows(): void
    {
        $handler = new PayrollLineProjectionHandler(new InMemoryPayrollLineReadModelRepository());

        $this->expectException(\LogicException::class);

        $handler->handle(new ManualAdjustmentAdded(self::EMPLOYEE_ID, new Money('10.00'), 'too early', new \DateTimeImmutable(), 1));
    }
}
