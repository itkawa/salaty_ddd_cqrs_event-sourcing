<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Application\Query;

use Payroll\Application\Query\GetPayrollLineQuery;
use Payroll\Application\Query\GetPayrollLineQueryHandler;
use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\Repository\InMemoryPayrollLineReadModelRepository;
use PHPUnit\Framework\TestCase;

final class GetPayrollLineQueryHandlerTest extends TestCase
{
    public function testReturnsNullForAnUnknownEmployee(): void
    {
        $handler = new GetPayrollLineQueryHandler(new InMemoryPayrollLineReadModelRepository());

        self::assertNull($handler->handle(new GetPayrollLineQuery('unknown')));
    }

    public function testReturnsTheStoredReadModel(): void
    {
        $repository = new InMemoryPayrollLineReadModelRepository();
        $line = PayrollLineReadModel::initialize('emp-001', new Money('1000.00'), new \DateTimeImmutable());
        $repository->save($line);

        $handler = new GetPayrollLineQueryHandler($repository);

        self::assertSame($line, $handler->handle(new GetPayrollLineQuery('emp-001')));
    }
}
