<?php

declare(strict_types=1);

namespace Payroll\Tests\Unit\Domain\Salary\ValueObject;

use Payroll\Domain\Salary\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testStoresAPositiveAmount(): void
    {
        self::assertSame('1000.00', (new Money('1000.00'))->value());
    }

    public function testStoresANegativeAmount(): void
    {
        self::assertSame('-45.55', (new Money('-45.55'))->value());
    }

    public function testNormalizesAWholeNumberToTwoDecimals(): void
    {
        self::assertSame('5.00', (new Money('5'))->value());
    }

    public function testNormalizesASingleDecimalDigit(): void
    {
        self::assertSame('5.10', (new Money('5.1'))->value());
    }

    public function testNormalizesNegativeZeroToPlainZero(): void
    {
        self::assertSame('0.00', (new Money('-0.00'))->value());
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsAnInvalidDecimalString(string $invalid): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money($invalid);
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'letters' => ['abc'],
            'empty string' => [''],
            'too many decimals' => ['1.234'],
            'trailing dot' => ['1.'],
            'double minus' => ['--5.00'],
            'thousands separator' => ['1,000.00'],
        ];
    }

    public function testAddSumsTwoPositiveAmounts(): void
    {
        $result = (new Money('1000.00'))->add(new Money('50.00'));

        self::assertSame('1050.00', $result->value());
    }

    public function testAddAppliesANegativeDelta(): void
    {
        $result = (new Money('1050.00'))->add(new Money('-45.55'));

        self::assertSame('1004.45', $result->value());
    }

    public function testAddReturnsANewInstanceAndDoesNotMutateOperands(): void
    {
        $base = new Money('1000.00');
        $delta = new Money('50.00');

        $result = $base->add($delta);

        self::assertSame('1000.00', $base->value());
        self::assertSame('50.00', $delta->value());
        self::assertSame('1050.00', $result->value());
    }

    public function testAddingOppositeAmountsResultsInPlainZeroNotNegativeZero(): void
    {
        $result = (new Money('45.55'))->add(new Money('-45.55'));

        self::assertSame('0.00', $result->value());
    }

    public function testIsNegativeIsTrueForANegativeAmount(): void
    {
        self::assertTrue((new Money('-45.55'))->isNegative());
    }

    public function testIsNegativeIsFalseForZeroAndPositiveAmounts(): void
    {
        self::assertFalse((new Money('0.00'))->isNegative());
        self::assertFalse((new Money('45.55'))->isNegative());
    }
}
