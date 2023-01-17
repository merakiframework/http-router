<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Config;
use PHPUnit\Framework\TestCase;

/**
 * @covers Config::
 */
final class ConfigTest extends TestCase
{
	/**
	 * @test
	 */
	public function a_validator_exists_for_validating_float_types(): void
	{
		$config = Config::create('Project\\Http');

		$this->assertArrayHasKey('float', $config->typeValidators);
	}

	/**
	 * @test
	 * @dataProvider validFloats
	 */
	public function validator_for_floats_should_validate_if_value_is_float(string $value): void
	{
		$config = Config::create('Project\\Http');
		$floatValidator = $config->typeValidators['float'];

		$this->assertTrue($floatValidator($value));
	}

	public function validFloats(): array
	{
		return [
			['1.0'],
			['3.14159'],
			['-2.5'],
			['-0.0'],
			['.27'],
			[' -2.3'],
			['+4'],
			['+4 '],
			['1e2'],
			['1E2'],
			['+1353.0316547'],
			['-8E+3'],
			['13213.03266e-13'],
			['8'],
		];
	}

	/**
	 * @test
	 * @dataProvider invalidFloats
	 */
	public function validator_for_floats_should_not_validate_if_value_not_float(string $value): void
	{
		$config = Config::create('Project\\Http');
		$floatValidator = $config->typeValidators['float'];

		$this->assertFalse($floatValidator($value));
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
