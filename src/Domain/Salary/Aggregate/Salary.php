<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\Aggregate;

use Payroll\Domain\Salary\Event\AutoPayrollAmountChanged;
use Payroll\Domain\Salary\Event\ManualAdjustmentAdded;
use Payroll\Domain\Salary\ValueObject\Money;

final class Salary
{
    private Money $currentAmount;

    private bool $hasBaseAmount = false;

    private bool $hasManualAdjustment = false;

    private int $version = 0;

    /** @var array<AutoPayrollAmountChanged|ManualAdjustmentAdded> */
    private array $uncommittedEvents = [];

    private function __construct(
        private readonly string $employeeId,
    ) {
        $this->currentAmount = new Money('0.00');
    }

    public static function forEmployee(string $employeeId): self
    {
        return new self($employeeId);
    }

    /** @param array<AutoPayrollAmountChanged|ManualAdjustmentAdded> $events */
    public static function rehydrate(string $employeeId, array $events): self
    {
        $salary = self::forEmployee($employeeId);

        foreach ($events as $event) {
            $salary->applyEvent($event);
        }

        return $salary;
    }

    public function addManualAdjustment(Money $adjustment, string $comment): void
    {
        if (!$this->hasBaseAmount) {
            throw new \LogicException('Cannot add a manual adjustment before an automatic amount has been applied.');
        }

        if ($adjustment->value() === '0.00') {
            throw new \InvalidArgumentException('A manual adjustment cannot be zero.');
        }

        if ($this->currentAmount->add($adjustment)->isNegative()) {
            throw new \InvalidArgumentException('A manual adjustment cannot bring the salary below zero.');
        }

        $comment = trim($comment);

        if ($comment === '') {
            throw new \InvalidArgumentException('A comment is required for a manual adjustment.');
        }

        $this->recordThat(new ManualAdjustmentAdded(
            employeeId: $this->employeeId,
            adjustment: $adjustment,
            comment: $comment,
            createdAt: new \DateTimeImmutable(),
            version: $this->version + 1,
        ));
    }

    /** Returns false if the amount was rejected because a manual adjustment already exists. */
    public function applyAutoCalculatedAmount(Money $baseAmount): bool
    {
        if ($baseAmount->value() === '0.00' || $baseAmount->isNegative()) {
            throw new \InvalidArgumentException('An automatically calculated amount must be greater than zero.');
        }

        if ($this->hasManualAdjustment) {
            return false;
        }

        $this->recordThat(new AutoPayrollAmountChanged(
            employeeId: $this->employeeId,
            baseAmount: $baseAmount,
            createdAt: new \DateTimeImmutable(),
            version: $this->version + 1,
        ));

        return true;
    }

    public function getCurrentAmount(): Money
    {
        return $this->currentAmount;
    }

    /** @return array<AutoPayrollAmountChanged|ManualAdjustmentAdded> */
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }

    public function clearUncommittedEvents(): void
    {
        $this->uncommittedEvents = [];
    }

    private function recordThat(AutoPayrollAmountChanged|ManualAdjustmentAdded $event): void
    {
        $this->applyEvent($event);
        $this->uncommittedEvents[] = $event;
    }

    private function applyEvent(AutoPayrollAmountChanged|ManualAdjustmentAdded $event): void
    {
        if ($event->version !== $this->version + 1) {
            throw new \RuntimeException(sprintf(
                'Unexpected event version for employee "%s": expected %d, got %d.',
                $this->employeeId,
                $this->version + 1,
                $event->version,
            ));
        }

        $this->currentAmount = match (true) {
            $event instanceof AutoPayrollAmountChanged => $event->baseAmount,
            $event instanceof ManualAdjustmentAdded => $this->currentAmount->add($event->adjustment),
        };

        if ($event instanceof AutoPayrollAmountChanged) {
            $this->hasBaseAmount = true;
        }

        if ($event instanceof ManualAdjustmentAdded) {
            $this->hasManualAdjustment = true;
        }

        $this->version = $event->version;
    }
}
