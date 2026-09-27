<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\Repository;

use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\Repository\InMemoryPayrollLineReadModelRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryPayrollLineReadModelRepositoryTest extends TestCase
{
    public function testFindReturnsNullWhenNothingWasSaved(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();

        self::assertNull($repository->find('emp-001'));
    }

    public function testSaveThenFindReturnsTheSameLine(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();
        $line = PayrollLineReadModel::initialize('emp-001', new Money('1000.00'), new \DateTimeImmutable());

        $repository->save($line);

        self::assertSame($line, $repository->find('emp-001'));
    }
}
