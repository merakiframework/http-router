<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\IncompleteValue;
use Project\Types\Slug;
use Project\Types\Year;
use Project\Types\Date;
use Project\Types\Month;
use Project\Types\Suit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueObjectCaster::class)]
final class ValueObjectCasterTest extends TestCase
{
	private ValueObjectCaster $caster;
	private CasterChain $chain;

	protected function setUp(): void
	{
		$this->caster = new ValueObjectCaster();
		// Full chain including the VO caster itself so nested value objects recurse.
		$this->chain = new CasterChain([
			new StringCaster(),
			new IntCaster(),
			new FloatCaster(),
			new ArrayCaster(),
			new EnumCaster(),
			new UuidCaster(),
			$this->caster,
		]);
	}

	#[Test()]
	public function supports_instantiable_classes_with_a_required_constructor_param(): void
	{
		$this->assertTrue($this->caster->supports(new Type(Year::class, false, false)));
		$this->assertTrue($this->caster->supports(new Type(Date::class, false, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
		$this->assertFalse($this->caster->supports(new Type(Suit::class, false, false)));
		$this->assertFalse($this->caster->supports(new Type(\Stringable::class, false, false)));
	}

	#[Test()]
	public function casts_a_single_string_param_value_object(): void
	{
		$result = $this->caster->cast(['my-post'], new Type(Slug::class, false, false), $this->chain);

		$this->assertInstanceOf(Slug::class, $result->value);
		$this->assertSame('my-post', $result->value->value);
		$this->assertSame(1, $result->consumed);
	}

	#[Test()]
	public function casts_a_nested_value_object_consuming_multiple_segments(): void
	{
		$result = $this->caster->cast(['2026', 'August', '27'], new Type(Date::class, false, false), $this->chain);

		$this->assertInstanceOf(Date::class, $result->value);
		$this->assertSame(2026, $result->value->year->value);
		$this->assertSame(Month::August, $result->value->month);
		// Day's int|string param resolves string-first (the universal StringCaster
		// wins on a union, same convention as int|string ids binding as strings).
		$this->assertSame('27', $result->value->day->value);
		$this->assertSame(3, $result->consumed);
	}

	#[Test()]
	public function throws_incomplete_when_not_enough_segments(): void
	{
		$this->expectException(IncompleteValue::class);

		$this->caster->cast(['2026', 'August'], new Type(Date::class, false, false), $this->chain);
	}

	#[Test()]
	public function throws_incomplete_when_no_segments(): void
	{
		$this->expectException(IncompleteValue::class);

		$this->caster->cast([], new Type(Slug::class, false, false), $this->chain);
	}
}
