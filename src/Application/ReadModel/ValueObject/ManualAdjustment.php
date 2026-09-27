<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel\ValueObject;

use Payroll\Domain\Salary\ValueObject\Money;

final class ManualAdjustment
{
    public function __construct(
        public readonly Money $adjustment,
        public readonly string $comment,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
