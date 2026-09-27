<?php

declare(strict_types=1);

namespace Payroll\Application\ReadModel;

use Payroll\Application\ReadModel\ValueObject\ManualAdjustment;
use Payroll\Domain\Salary\ValueObject\Money;

final class PayrollLineReadModel
{
    /** @var ManualAdjustment[] */
    private array $manualAdjustments = [];

    private function __construct(
        private readonly string $employeeId,
        private Money $baseAmount,
        private Money $currentAmount,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function initialize(string $employeeId, Money $baseAmount, \DateTimeImmutable $occurredAt): self
    {
        return new self($employeeId, $baseAmount, $baseAmount, $occurredAt);
    }

    public function employeeId(): string
    {
        return $this->employeeId;
    }

    public function baseAmount(): Money
    {
        return $this->baseAmount;
    }

    public function currentAmount(): Money
    {
        return $this->currentAmount;
    }

    /** @return ManualAdjustment[] */
    public function manualAdjustments(): array
    {
        return $this->manualAdjustments;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function recordAutomaticRecalculation(Money $baseAmount, \DateTimeImmutable $occurredAt): void
    {
        $this->baseAmount = $baseAmount;
        $this->currentAmount = $baseAmount;
        $this->updatedAt = $occurredAt;
    }

    public function recordManualAdjustment(Money $adjustment, string $comment, \DateTimeImmutable $occurredAt): void
    {
        $this->manualAdjustments[] = new ManualAdjustment($adjustment, $comment, $occurredAt);
        $this->currentAmount = $this->currentAmount->add($adjustment);
        $this->updatedAt = $occurredAt;
    }
}
