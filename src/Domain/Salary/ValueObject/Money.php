<?php

declare(strict_types=1);

namespace Payroll\Domain\Salary\ValueObject;

final class Money
{
    private const SCALE = 2;

    private readonly string $amount;

    public function __construct(string $amount)
    {
        $this->assertValidDecimal($amount);

        $this->amount = bcadd($amount, '0', self::SCALE);
    }

    public function add(Money $other): Money
    {
        return new self(bcadd($this->amount, $other->amount, self::SCALE));
    }

    public function value(): string
    {
        return $this->amount;
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->amount, '-');
    }

    private function assertValidDecimal(string $amount): void
    {
        if (!preg_match('/^-?\d+(\.\d{1,' . self::SCALE . '})?$/', $amount)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid decimal monetary amount.', $amount));
        }
    }
}
