<?php

declare(strict_types=1);

namespace Payroll\Application\Logging;

interface RejectedRecalculationLogInterface
{
    public function record(RejectedAutoRecalculation $entry): void;

    /** @return RejectedAutoRecalculation[] */
    public function allFor(string $employeeId): array;
}
