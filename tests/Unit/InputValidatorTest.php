<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\InputValidator;
use App\Support\ValidationException;
use PHPUnit\Framework\TestCase;

final class InputValidatorTest extends TestCase
{
    public function testTextIsTrimmed(): void
    {
        self::assertSame('Public API', InputValidator::text('  Public API  ', 'Name', 120));
    }

    public function testRequiredTextRejectsEmptyValue(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::text(' ', 'Name', 120);
    }

    public function testTextRejectsExcessiveLength(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::text('abcd', 'Name', 3);
    }

    public function testIntegerAcceptsConfiguredRange(): void
    {
        self::assertSame(100, InputValidator::integer('100', 'Display order', 0, 1000));
    }

    public function testIntegerRejectsOutOfRangeValue(): void
    {
        $this->expectException(ValidationException::class);
        InputValidator::integer('-1', 'Display order', 0, 1000);
    }
}
