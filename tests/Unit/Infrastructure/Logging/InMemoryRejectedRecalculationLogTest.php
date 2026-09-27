<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Infrastructure\Logging;

use Payroll\Application\Logging\RejectedAutoRecalculation;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\Logging\InMemoryRejectedRecalculationLog;
use PHPUnit\Framework\TestCase;

final class InMemoryRejectedRecalculationLogTest extends TestCase
{
    public function testAllForReturnsNoEntriesWhenNothingWasRecorded(): void
    {
        $log = new InMemoryRejectedRecalculationLog();

        self::assertSame([], $log->allFor('emp-001'));
    }

    public function testRecordStoresAnEntryRetrievableByEmployeeId(): void
    {
        $log = new InMemoryRejectedRecalculationLog();
        $occurredAt = new \DateTimeImmutable();

        $log->record(new RejectedAutoRecalculation('emp-001', new Money('9999.99'), $occurredAt));

        $entries = $log->allFor('emp-001');
        self::assertCount(1, $entries);
        self::assertSame('emp-001', $entries[0]->employeeId);
        self::assertSame('9999.99', $entries[0]->attemptedAmount->value());
        self::assertSame($occurredAt, $entries[0]->occurredAt);
    }

    public function testAllForFiltersByEmployeeId(): void
    {
        $log = new InMemoryRejectedRecalculationLog();

        $log->record(new RejectedAutoRecalculation('emp-001', new Money('100.00'), new \DateTimeImmutable()));
        $log->record(new RejectedAutoRecalculation('emp-002', new Money('200.00'), new \DateTimeImmutable()));

        self::assertCount(1, $log->allFor('emp-001'));
        self::assertCount(1, $log->allFor('emp-002'));
        self::assertCount(0, $log->allFor('emp-003'));
    }
}
