<?php

declare(strict_types=1);

namespace Payroll\Demo;

use Payroll\Domain\Salary\ValueObject\Money;

/**
 * Stands in for the real payroll calculation service (out of scope for this
 * PoC, see readme.md). Not a domain concept — purely a demo/CLI helper that
 * produces a plausible amount so ApplyAutoCalculatedAmountCommand has
 * something to carry.
 */
final class DemoCalculator
{
    public function calculate(): Money
    {
        $cents = random_int(100_000, 500_000);

        return new Money(number_format($cents / 100, 2, '.', ''));
    }
}
