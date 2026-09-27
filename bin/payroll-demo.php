#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Payroll\Application\Command\AddManualAdjustmentCommand;
use Payroll\Application\Command\ApplyAutoCalculatedAmountCommand;
use Payroll\Application\Handler\AddManualAdjustmentCommandHandler;
use Payroll\Application\Handler\ApplyAutoCalculatedAmountCommandHandler;
use Payroll\Application\Projection\PayrollLineProjectionHandler;
use Payroll\Application\Query\GetPayrollLineQuery;
use Payroll\Application\Query\GetPayrollLineQueryHandler;
use Payroll\Application\ReadModel\PayrollLineReadModel;
use Payroll\Domain\Salary\ValueObject\Money;
use Payroll\Infrastructure\EventStore\InMemoryPayrollLineEventStore;
use Payroll\Infrastructure\Repository\PayrollLineReadModelRepository;
use Payroll\Infrastructure\Repository\SalaryRepository;

function money(string $value): string
{
    $amount = (float) $value;

    return ($amount < 0 ? '-' : '') . '$' . number_format(abs($amount), 2);
}

function printCurrentValue(GetPayrollLineQueryHandler $queryHandler, string $employeeId): void
{
    $line = $queryHandler->handle(new GetPayrollLineQuery($employeeId));
    echo '  -> Current value: ' . money($line->currentAmount()->value()) . "\n\n";
}

// Wiring — no container needed for a PoC, plain composition.
$eventStore = new InMemoryPayrollLineEventStore();
$salaryRepository = new SalaryRepository($eventStore);
$readModelRepository = new PayrollLineReadModelRepository();
$projectionHandler = new PayrollLineProjectionHandler($readModelRepository);
$autoHandler = new ApplyAutoCalculatedAmountCommandHandler($salaryRepository, $projectionHandler);
$manualHandler = new AddManualAdjustmentCommandHandler($salaryRepository, $projectionHandler);
$queryHandler = new GetPayrollLineQueryHandler($readModelRepository);

$employeeId = 'emp-001';

echo "=== Payroll Line Demo (Event Sourcing) ===\n\n";

echo "Step 1: System calculates the line\n";
$autoHandler->handle(new ApplyAutoCalculatedAmountCommand($employeeId, new Money('1000.00')));
printCurrentValue($queryHandler, $employeeId);

echo "Step 2: Source data changes, system recalculates (no manual correction yet, so this is allowed)\n";
$autoHandler->handle(new ApplyAutoCalculatedAmountCommand($employeeId, new Money('1050.00')));
printCurrentValue($queryHandler, $employeeId);

echo "Step 3: Specialist adds a manual correction (-\$45.55): \"Employee declined dental benefit; reversing deduction\"\n";
$manualHandler->handle(new AddManualAdjustmentCommand($employeeId, new Money('-45.55'), 'Employee declined dental benefit; reversing deduction'));
printCurrentValue($queryHandler, $employeeId);

echo "Step 4: Source data changes again, system attempts to recalculate (must be ignored — line already has a manual correction)\n";
$autoHandler->handle(new ApplyAutoCalculatedAmountCommand($employeeId, new Money('9999.99')));
printCurrentValue($queryHandler, $employeeId);

echo "Step 5: Specialist adds a second correction (+\$100.10): \"Late correction: missed approved overtime bonus\"\n";
$manualHandler->handle(new AddManualAdjustmentCommand($employeeId, new Money('100.10'), 'Late correction: missed approved overtime bonus'));
printCurrentValue($queryHandler, $employeeId);

echo "Step 6: Specialist adds a third correction (-\$0.10): \"Minor rounding adjustment\"\n";
$manualHandler->handle(new AddManualAdjustmentCommand($employeeId, new Money('-0.10'), 'Minor rounding adjustment'));
printCurrentValue($queryHandler, $employeeId);

echo "Step 7: Specialist adds a fourth correction (-\$0.20): \"Second minor rounding adjustment\"\n";
$manualHandler->handle(new AddManualAdjustmentCommand($employeeId, new Money('-0.20'), 'Second minor rounding adjustment'));
printCurrentValue($queryHandler, $employeeId);

echo "Step 8: Specialist adds a compensating correction (+\$0.20): \"Correcting mistake in adjustment #4\"\n";
$manualHandler->handle(new AddManualAdjustmentCommand($employeeId, new Money('0.20'), 'Correcting mistake in adjustment #4'));
printCurrentValue($queryHandler, $employeeId);

echo "=== Final audit history ===\n";
/** @var PayrollLineReadModel $line */
$line = $queryHandler->handle(new GetPayrollLineQuery($employeeId));

echo 'System value (frozen at step 3): ' . money($line->baseAmount()->value()) . "\n";

foreach ($line->manualAdjustments() as $index => $adjustment) {
    $sign = str_starts_with($adjustment->adjustment->value(), '-') ? '' : '+';
    echo sprintf(
        "Adjustment %d: %s%s — %s\n",
        $index + 1,
        $sign,
        money($adjustment->adjustment->value()),
        $adjustment->comment,
    );
}

echo 'Current (new) value: ' . money($line->currentAmount()->value()) . "\n\n";

echo "=== Event Sourcing check: rehydrating Salary from the Event Store ===\n";
$rehydrated = $salaryRepository->load($employeeId);
echo 'Rehydrated current amount: ' . money($rehydrated->getCurrentAmount()->value())
    . ($rehydrated->getCurrentAmount()->value() === $line->currentAmount()->value() ? ' (matches read model)' : ' (MISMATCH!)')
    . "\n\n";

echo "=== Bonus: business rule enforcement ===\n";
try {
    $manualHandler->handle(new AddManualAdjustmentCommand('emp-with-no-base-amount', new Money('10.00'), 'should be rejected'));
    echo "FAIL: expected an exception, none was thrown\n";
} catch (\LogicException $e) {
    echo "OK — manual adjustment before any automatic amount was correctly rejected:\n";
    echo '  ' . $e->getMessage() . "\n";
}
