<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;
use Project\Types\Suit;
use Project\Types\Priority;
use Project\Types\Direction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnumCaster::class)]
final class EnumCasterTest extends TestCase
{
	private EnumCaster $caster;
	private CasterChain $chain;

	protected function setUp(): void
	{
		$this->caster = new EnumCaster();
		$this->chain = new CasterChain([]);
	}

	#[Test()]
	public function supports_enums_only(): void
	{
		$this->assertTrue($this->caster->supports(new Type(Suit::class, false, false)));
		$this->assertFalse($this->caster->supports(new Type('int', true, false)));
		$this->assertFalse($this->caster->supports(new Type(\stdClass::class, false, false)));
	}

	#[Test()]
	public function casts_a_string_backed_enum(): void
	{
		$result = $this->caster->cast(['hearts'], new Type(Suit::class, false, false), $this->chain);

		$this->assertSame(Suit::Hearts, $result->value);
		$this->assertSame(1, $result->consumed);
	}

	#[Test()]
	public function casts_an_int_backed_enum(): void
	{
		$result = $this->caster->cast(['3'], new Type(Priority::class, false, false), $this->chain);

		$this->assertSame(Priority::High, $result->value);
	}

	#[Test()]
	public function casts_an_unbacked_enum_by_case_name(): void
	{
		$result = $this->caster->cast(['North'], new Type(Direction::class, false, false), $this->chain);

		$this->assertSame(Direction::North, $result->value);
	}

	#[Test()]
	public function rejects_an_unknown_backed_value(): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast(['joker'], new Type(Suit::class, false, false), $this->chain);
	}

	#[Test()]
	public function rejects_an_unknown_int_value(): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast(['99'], new Type(Priority::class, false, false), $this->chain);
	}

	#[Test()]
	public function rejects_a_non_integer_for_an_int_backed_enum(): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast(['high'], new Type(Priority::class, false, false), $this->chain);
	}

	#[Test()]
	public function unbacked_match_is_case_insensitive(): void
	{
		// The router lower-cases the request target, so a PascalCase case name
		// must still match its lower-cased URL form.
		$result = $this->caster->cast(['north'], new Type(Direction::class, false, false), $this->chain);

		$this->assertSame(Direction::North, $result->value);
	}

	#[Test()]
	public function rejects_an_unknown_case_name(): void
	{
		$this->expectException(CannotCast::class);

		$this->caster->cast(['nowhere'], new Type(Direction::class, false, false), $this->chain);
	}

	#[Test()]
	public function throws_incomplete_when_no_segments(): void
	{
		$this->expectException(IncompleteValue::class);

		$this->caster->cast([], new Type(Suit::class, false, false), $this->chain);
	}
}
