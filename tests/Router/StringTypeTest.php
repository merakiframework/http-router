<?php

declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\StringType;
use PHPUnit\Framework\TestCase;

/**
 * @covers StringType::
 */
final class StringTypeTest extends TestCase
{
	/**
	 * @test
	 * @dataProvider validFloatsAlreadyNormalised
	 */
	public function can_convert_floats_if_value_is_a_float(string $value): void
	{
		$str = StringType::fromString($value);

		$castedValue = $str->castTo('float');

		$this->assertTrue(is_float($castedValue));
		$this->assertTrue($str->equals(StringType::fromFloat($castedValue)));
	}

	public function validFloatsAlreadyNormalised(): array
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

	public function validFloatsNotNormalised(): array
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

	/**
	 * @test
	 * @dataProvider invalidFloats
	 */
	public function returns_null_if_value_is_not_a_float(string $value): void
	{
		$exception = new \RuntimeException('Cannot cast to float: casting will lose information.');
		$str = StringType::fromString($value);

		$this->expectExceptionObject($exception);

		$castedValue = $str->castTo('float');
	}

	public function invalidFloats(): array
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
}
