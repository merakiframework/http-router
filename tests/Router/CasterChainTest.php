<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Type;
use Meraki\Http\Router\Exception\CannotCast;
use Meraki\Http\Router\Exception\IncompleteValue;
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

		$this->assertSame(2026, $result->value);
		$this->assertSame(1, $result->consumed);
	}

	#[Test()]
	public function falls_through_to_next_union_member_when_value_is_invalid(): void
	{
		$chain = new CasterChain([new IntCaster(), new StringCaster()]);

		$result = $chain->cast(['abc'], $this->int, $this->string);

		$this->assertSame('abc', $result->value);
	}

	#[Test()]
	public function skips_the_null_type(): void
	{
		$chain = new CasterChain([new StringCaster()]);

		$result = $chain->cast(['x'], new Type('null', true, true), $this->string);

		$this->assertSame('x', $result->value);
	}

	#[Test()]
	public function throws_when_no_caster_supports_the_type(): void
	{
		$this->expectException(CannotCast::class);

		(new CasterChain([]))->cast(['x'], $this->int);
	}

	#[Test()]
	public function propagates_incomplete_when_segments_run_out(): void
	{
		$this->expectException(IncompleteValue::class);

		(new CasterChain([new IntCaster()]))->cast([], $this->int);
	}
}
