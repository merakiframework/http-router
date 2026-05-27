<?php

declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\StringType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StringType::class)]
final class StringTypeTest extends TestCase
{
	#[Test()]
	#[DataProvider('validFloatsAlreadyNormalised')]
	public function can_convert_floats_if_value_is_a_float(string $value): void
	{
		$str = StringType::fromString($value);

		$castedValue = $str->castTo('float');

		$this->assertTrue(is_float($castedValue));
		$this->assertTrue($str->equals(StringType::fromFloat($castedValue)));
	}

	public static function validFloatsAlreadyNormalised(): array
	{
		return [
			['3.14159'],
			['-2.5'],
			['0.27'],
			['1353.0316547'],
			['99.9'],
			['1.321303266E-9'],
			['-5000.12'],
			['6.02E+23'],
			['-3.0E-45'],
		];
	}

	public static function validFloatsNotNormalised(): array
	{
		return [
			['-8000'],
			['8'],
			['1.0', '1'],
			['-0.0', '-0'],
			['+4', '4'],
			['1e2', '100'],
			['1E2', '100'],
			['+1353.0316547', '1353.0316547'],
			['-8E+3', '-8000'],
			['13213.03266e-13', '1.321303266E-9'],
			['6.02e23', '6.02E+23'],
			['-3e-45', '-3.0E-45'],
		];
	}

	#[Test()]
	#[DataProvider('invalidFloats')]
	public function returns_null_if_value_is_not_a_float(string $value): void
	{
		$exception = new \RuntimeException('Cannot cast to float: casting will lose information.');
		$str = StringType::fromString($value);

		$this->expectExceptionObject($exception);

		$castedValue = $str->castTo('float');
	}

	public static function invalidFloats(): array
	{
		return [
			['e2'],
			['abc'],
			['-e-4'],
			['E'],
			['abc.def'],
			['8e'],
			['E2'],
			['8E'],
			['-'],
			['+'],
			['.a'],
			['a.'],
			['-1.a'],
			['.'],
			['-.'],
			['1+'],
		];
	}

	#[Test()]
	#[DataProvider('validInts')]
	public function can_convert_ints_if_value_is_an_int(string $value, int $expected): void
	{
		$str = StringType::fromString($value);

		$castedValue = $str->castTo('int');

		$this->assertSame($expected, $castedValue);
	}

	public static function validInts(): array
	{
		return [
			'plain digit' => ['5', 5],
			'zero' => ['0', 0],
			'leading zero (single)' => ['05', 5],
			'leading zeros (multiple)' => ['007', 7],
			'all zeros' => ['000', 0],
			'negative' => ['-5', -5],
			'negative with leading zero' => ['-05', -5],
			'large value' => ['2026', 2026],
		];
	}

	#[Test()]
	#[DataProvider('invalidInts')]
	public function returns_null_if_value_is_not_an_int(string $value): void
	{
		$exception = new RuntimeException('Cannot cast to integer: value is not a valid integer.');
		$str = StringType::fromString($value);

		$this->expectExceptionObject($exception);

		$castedValue = $str->castTo('int');
	}

	public static function invalidInts(): array
	{
		return [
			'alphabetic' => ['abc'],
			'decimal' => ['1.5'],
			'leading plus' => ['+5'],
			'trailing junk' => ['5abc'],
			'leading whitespace' => [' 5'],
			'empty string' => [''],
			'lone minus' => ['-'],
		];
	}

	#[Test()]
	#[DataProvider('validArrays')]
	public function can_convert_to_array_if_value_is_array_like(string $value, array $expected): void
	{
		$str = StringType::fromString($value);

		$castedValue = $str->castTo('array');

		$this->assertTrue(array_is_list($castedValue));
		$this->assertEquals($expected, $castedValue);
	}

	public static function validArrays(): array
	{
		return [
			'integers list' => ['1,2,3', [1,2,3]],
			'strings list' => ['one,two,three', ['one','two','three']],
			'floats list' => ['3.14159,1.61803,2.71828', [3.14159,1.61803,2.71828]],
		];
	}

	#[Test()]
	#[DataProvider('invalidArrays')]
	public function throws_exception_if_value_cannot_be_cast_to_array(string $value, string $message): void
	{
		$exception = new RuntimeException($message);
		$str = StringType::fromString($value);

		$this->expectExceptionObject($exception);

		$castedValue = $str->castTo('array');
	}

	public static function invalidArrays(): array
	{
		$missingElementMessage = 'Cannot cast to array: missing element in list';

		return [
			'empty list' => ['', 'Cannot cast to array: array is empty'],
			'leading comma in list' => [',two,three', $missingElementMessage],
			'trailing comma in list' => ['one,two,', $missingElementMessage],
			'missing element' => ['one,,three', $missingElementMessage],
			'different types' => ['1,one,3.14159', 'cannot cast to array: elements are not all of the same type'],
		];
	}
}
