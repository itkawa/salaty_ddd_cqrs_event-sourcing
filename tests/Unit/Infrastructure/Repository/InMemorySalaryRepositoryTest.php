<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\Repository;

use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use Payroll\Infrastructure\Repository\InMemorySalaryRepository;
use PHPUnit\Framework\TestCase;

final class InMemorySalaryRepositoryTest extends TestCase
{
    public function testLoadingAnUnknownEmployeeReturnsAFreshSalary(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());

        $salary = $repository->load('emp-001');

        self::assertSame('0.00', $salary->getCurrentAmount()->value());
    }

    public function testSaveAppendsUncommittedEventsAndClearsThem(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());
        $salary = $repository->load('emp-001');
        $salary->applyAutoCalculatedAmount(new Money('1000.00'));

        $events = $repository->save($salary);

        self::assertCount(1, $events);
        self::assertSame([], $salary->getUncommittedEvents());
    }

    public function testLoadAfterSaveRehydratesTheSameState(): void
    {
        $repository = new InMemorySalaryRepository(new InMemoryPayrollLineEventStore());
        $salary = $repository->load('emp-001');
        $salary->applyAutoCalculatedAmount(new Money('1000.00'));
        $repository->save($salary);

        $reloaded = $repository->load('emp-001');

        self::assertSame('1000.00', $reloaded->getCurrentAmount()->value());
    }
}
