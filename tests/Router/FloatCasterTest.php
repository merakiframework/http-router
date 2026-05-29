<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FloatCaster::class)]
final class FloatCasterTest extends TestCase
{
	private FloatCaster $caster;
	private Type $floatType;

	protected function setUp(): void
	{
		$this->caster = new FloatCaster();
		$this->floatType = new Type('float', true, false);
	}

	#[Test()]
	public function supports_only_float(): void
	{
		$this->assertTrue($this->caster->supports(new Type('float', true, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
	}

	#[Test()]
	#[DataProvider('validFloats')]
	public function casts_values_that_round_trip(string $value): void
	{
		$casted = $this->caster->cast($value, $this->floatType);

		$this->assertIsFloat($casted);
		$this->assertSame($value, (string) $casted);
	}

	/**
	 * @return array<int, array{string}>
	 */
	public static function validFloats(): array
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

	#[Test()]
	#[DataProvider('invalidFloats')]
	public function throws_when_value_would_lose_information(string $value): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast($value, $this->floatType);
	}

	/**
	 * @return array<int, array{string}>
	 */
	public static function invalidFloats(): array
	{
		return [
			['e2'], ['abc'], ['-e-4'], ['E'], ['abc.def'], ['8e'], ['E2'],
			['8E'], ['-'], ['+'], ['.a'], ['a.'], ['-1.a'], ['.'], ['-.'], ['1+'],
		];
	}
}
