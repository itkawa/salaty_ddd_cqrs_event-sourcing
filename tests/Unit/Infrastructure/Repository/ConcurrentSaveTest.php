<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\Repository;

use Payroll\Domain\Salary\Repository\ConcurrencyException;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use Payroll\Infrastructure\Repository\InMemorySalaryRepository;
use PHPUnit\Framework\TestCase;

final class ConcurrentSaveTest extends TestCase
{
    public function testASecondConcurrentSaveForTheSameEmployeeIsRejectedWithoutCorruptingTheStream(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());

        $first = $repository->load('e1');
        $second = $repository->load('e1');

        $first->applyAutoCalculatedAmount(new Money('100.00'));
        $repository->save($first);

        $second->applyAutoCalculatedAmount(new Money('200.00'));

        try {
            $repository->save($second);
            self::fail('Expected a ConcurrencyException.');
        } catch (ConcurrencyException) {
            // expected
        }

        // The stream must stay uncorrupted: still readable, still just the first save.
        $reloaded = $repository->load('e1');
        self::assertSame('100.00', $reloaded->getCurrentAmount()->value());
    }

    public function testDifferentEmployeesCanBeSavedWithoutConflict(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());

        $employeeA = $repository->load('e1');
        $employeeB = $repository->load('e2');

        $employeeA->applyAutoCalculatedAmount(new Money('100.00'));
        $employeeB->applyAutoCalculatedAmount(new Money('200.00'));

        $repository->save($employeeA);
        $repository->save($employeeB);

        self::assertSame('100.00', $repository->load('e1')->getCurrentAmount()->value());
        self::assertSame('200.00', $repository->load('e2')->getCurrentAmount()->value());
    }

    public function testASecondSequentialSaveForTheSameEmployeeStillWorks(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());

        $salary = $repository->load('e1');
        $salary->applyAutoCalculatedAmount(new Money('100.00'));
        $repository->save($salary);

        $salary->applyAutoCalculatedAmount(new Money('150.00'));
        $repository->save($salary);

        self::assertSame('150.00', $repository->load('e1')->getCurrentAmount()->value());
    }
}
