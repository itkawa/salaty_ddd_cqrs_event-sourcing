<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Repository;

/**
 * Thrown by a SalaryRepositoryInterface implementation when appending an event
 * would conflict with what's already persisted for the same employeeId —
 * i.e. the aggregate was loaded, changed, and saved concurrently from more
 * than one place.
 */
final class ConcurrencyException extends \RuntimeException
{
}
