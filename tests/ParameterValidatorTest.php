<?php

declare(strict_types=1);

namespace zRoute\Tests;

use PHPUnit\Framework\TestCase;
use zRoute\Exceptions\ValidationException;
use zRoute\ParameterValidator;

/**
 * Unit tests for the ParameterValidator class.
 */
class ParameterValidatorTest extends TestCase
{
    private ParameterValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ParameterValidator();
    }

    // -----------------------------------------------------------------------
    // Required / optional / defaults
    // -----------------------------------------------------------------------

    public function testMissingRequiredParamThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['name' => ['type' => 'string', 'required' => true]],
            [],
        );
    }

    public function testMissingOptionalParamIsOmittedFromResult(): void
    {
        $result = $this->validator->validate(
            ['filter' => ['type' => 'string', 'required' => false]],
            [],
        );

        $this->assertArrayNotHasKey('filter', $result);
    }

    public function testDefaultValueIsUsedWhenParamAbsent(): void
    {
        $result = $this->validator->validate(
            ['page' => ['type' => 'integer', 'required' => false, 'default' => 1]],
            [],
        );

        $this->assertSame(1, $result['page']);
    }

    public function testValidationExceptionCarriesPerFieldErrors(): void
    {
        try {
            $this->validator->validate(
                [
                    'a' => ['type' => 'string', 'required' => true],
                    'b' => ['type' => 'string', 'required' => true],
                ],
                [],
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('a', $e->getErrors());
            $this->assertArrayHasKey('b', $e->getErrors());
        }
    }

    // -----------------------------------------------------------------------
    // Type casting — integer
    // -----------------------------------------------------------------------

    public function testIntegerCastFromString(): void
    {
        $result = $this->validator->validate(
            ['id' => ['type' => 'integer']],
            ['id' => '42'],
        );

        $this->assertSame(42, $result['id']);
    }

    public function testIntegerPassthroughWhenAlreadyInt(): void
    {
        $result = $this->validator->validate(
            ['id' => ['type' => 'integer']],
            ['id' => 42],
        );

        $this->assertSame(42, $result['id']);
    }

    public function testInvalidIntegerThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['id' => ['type' => 'integer']],
            ['id' => 'not-a-number'],
        );
    }

    // -----------------------------------------------------------------------
    // Type casting — float
    // -----------------------------------------------------------------------

    public function testFloatCastFromString(): void
    {
        $result = $this->validator->validate(
            ['price' => ['type' => 'float']],
            ['price' => '9.99'],
        );

        $this->assertEqualsWithDelta(9.99, $result['price'], 0.001);
    }

    public function testInvalidFloatThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['price' => ['type' => 'float']],
            ['price' => 'cheap'],
        );
    }

    // -----------------------------------------------------------------------
    // Type casting — boolean
    // -----------------------------------------------------------------------

    public function testBooleanCastFromStringTrue(): void
    {
        foreach (['1', 'true', 'yes'] as $raw) {
            $result = $this->validator->validate(
                ['active' => ['type' => 'boolean']],
                ['active' => $raw],
            );
            $this->assertTrue($result['active'], "Expected true for input '{$raw}'");
        }
    }

    public function testBooleanCastFromStringFalse(): void
    {
        foreach (['0', 'false', 'no'] as $raw) {
            $result = $this->validator->validate(
                ['active' => ['type' => 'boolean']],
                ['active' => $raw],
            );
            $this->assertFalse($result['active'], "Expected false for input '{$raw}'");
        }
    }

    public function testBooleanPassthroughNative(): void
    {
        $result = $this->validator->validate(
            ['active' => ['type' => 'boolean']],
            ['active' => true],
        );

        $this->assertTrue($result['active']);
    }

    public function testInvalidBooleanThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['active' => ['type' => 'boolean']],
            ['active' => 'maybe'],
        );
    }

    // -----------------------------------------------------------------------
    // Type casting — string
    // -----------------------------------------------------------------------

    public function testStringCastFromInt(): void
    {
        $result = $this->validator->validate(
            ['label' => ['type' => 'string']],
            ['label' => 42],
        );

        $this->assertSame('42', $result['label']);
    }

    // -----------------------------------------------------------------------
    // Type casting — array
    // -----------------------------------------------------------------------

    public function testArrayPassthrough(): void
    {
        $result = $this->validator->validate(
            ['tags' => ['type' => 'array']],
            ['tags' => ['php', 'routing']],
        );

        $this->assertSame(['php', 'routing'], $result['tags']);
    }

    public function testArrayWithStructureCastsSubKeys(): void
    {
        $result = $this->validator->validate(
            [
                'meta' => [
                    'type'      => 'array',
                    'structure' => [
                        'retry_count'   => 'integer',
                        'reference_key' => 'string',
                    ],
                ],
            ],
            ['meta' => ['retry_count' => '3', 'reference_key' => 'abc']],
        );

        $this->assertSame(3, $result['meta']['retry_count']);
        $this->assertSame('abc', $result['meta']['reference_key']);
    }

    public function testNonArrayThrowsForArrayType(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['tags' => ['type' => 'array']],
            ['tags' => 'not-an-array'],
        );
    }

    // -----------------------------------------------------------------------
    // Path-param regex constraint
    // -----------------------------------------------------------------------

    public function testRegexConstraintPassesForMatchingValue(): void
    {
        $result = $this->validator->validate(
            ['id' => ['type' => 'string', 'source' => 'path', 'regex' => '[0-9]+']],
            ['id' => '123'],
        );

        $this->assertSame('123', $result['id']);
    }

    public function testRegexConstraintFailsForNonMatchingValue(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(
            ['id' => ['type' => 'string', 'source' => 'path', 'regex' => '[0-9]+']],
            ['id' => 'abc'],
        );
    }

    public function testRegexIsOnlyAppliedToPathSource(): void
    {
        // source=query: regex should NOT be applied — 'abc' should pass.
        $result = $this->validator->validate(
            ['id' => ['type' => 'string', 'source' => 'query', 'regex' => '[0-9]+']],
            ['id' => 'abc'],
        );

        $this->assertSame('abc', $result['id']);
    }
}
