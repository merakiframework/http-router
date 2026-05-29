<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntCaster::class)]
final class IntCasterTest extends TestCase
{
	private IntCaster $caster;
	private CasterChain $chain;
	private Type $intType;

	protected function setUp(): void
	{
		$this->caster = new IntCaster();
		$this->chain = new CasterChain([]);
		$this->intType = new Type('int', true, false);
	}

	#[Test()]
	public function supports_only_int(): void
	{
		$this->assertTrue($this->caster->supports(new Type('int', true, false)));
		$this->assertFalse($this->caster->supports(new Type('string', true, false)));
		$this->assertFalse($this->caster->supports(new Type('float', true, false)));
	}

	#[Test()]
	#[DataProvider('validInts')]
	public function casts_valid_ints(string $value, int $expected): void
	{
		$result = $this->caster->cast([$value], $this->intType, $this->chain);

		$this->assertSame($expected, $result->value);
		$this->assertSame(1, $result->consumed);
	}

	/**
	 * @return array<string, array{string, int}>
	 */
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
	public function throws_on_invalid_int(string $value): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast([$value], $this->intType, $this->chain);
	}

	/**
	 * @return array<string, array{string}>
	 */
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
	public function throws_incomplete_when_no_segments(): void
	{
		$this->expectException(IncompleteValue::class);

		$this->caster->cast([], $this->intType, $this->chain);
	}
}
