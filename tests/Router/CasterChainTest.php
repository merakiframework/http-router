<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CasterChain::class)]
final class CasterChainTest extends TestCase
{
	private Type $int;
	private Type $string;

	protected function setUp(): void
	{
		$this->int = new Type('int', true, false);
		$this->string = new Type('string', true, false);
	}

	#[Test()]
	public function first_supporting_caster_in_a_union_wins(): void
	{
		$chain = new CasterChain([new IntCaster(), new StringCaster()]);

		$result = $chain->cast(['2026'], $this->int, $this->string);

		$this->assertSame(CastStatus::Successful, $result->status);
		$this->assertSame(2026, $result->value);
		$this->assertSame(1, $result->consumed);
	}

	#[Test()]
	public function falls_through_to_next_union_member_when_value_is_invalid(): void
	{
		$chain = new CasterChain([new IntCaster(), new StringCaster()]);

		$result = $chain->cast(['abc'], $this->int, $this->string);

		$this->assertSame(CastStatus::Successful, $result->status);
		$this->assertSame('abc', $result->value);
	}

	#[Test()]
	public function skips_the_null_type(): void
	{
		$chain = new CasterChain([new StringCaster()]);

		$result = $chain->cast(['x'], new Type('null', true, true), $this->string);

		$this->assertSame(CastStatus::Successful, $result->status);
		$this->assertSame('x', $result->value);
	}

	#[Test()]
	public function returns_cannot_cast_when_no_caster_supports_the_type(): void
	{
		$result = (new CasterChain([]))->cast(['x'], $this->int);

		$this->assertSame(CastStatus::CannotCast, $result->status);
	}

	#[Test()]
	public function returns_incomplete_when_called_with_no_segments(): void
	{
		$result = (new CasterChain([new IntCaster()]))->cast([], $this->int);

		$this->assertSame(CastStatus::IncompleteValue, $result->status);
	}

	#[Test()]
	public function propagates_incomplete_from_a_caster(): void
	{
		// A caster that always reports incomplete propagates through the chain.
		$alwaysIncomplete = new class () implements Caster {
			public function supports(Type $type): bool { return $type->name === 'int'; }
			public function cast(array $segments, Type $type, CasterChain $chain): CastResult
			{
				return CastResult::incomplete();
			}
		};

		$result = (new CasterChain([$alwaysIncomplete]))->cast(['x'], $this->int);

		$this->assertSame(CastStatus::IncompleteValue, $result->status);
	}
}
