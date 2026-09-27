<?php

declare(strict_types=1);

namespace Payroll\Infrastructure\Logging;

use Payroll\Application\Logging\RejectedAutoRecalculation;
use Payroll\Application\Logging\RejectedRecalculationLogInterface;

final class InMemoryRejectedRecalculationLog implements RejectedRecalculationLogInterface
{
    /** @var RejectedAutoRecalculation[] */
    private array $entries = [];

    public function record(RejectedAutoRecalculation $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return RejectedAutoRecalculation[] */
    public function allFor(string $employeeId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (RejectedAutoRecalculation $entry): bool => $entry->employeeId === $employeeId,
        ));
    }
}
