<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Config;
use Meraki\Http\Router\ValueObjectCaster;
use Project\Types\Suit;
use Project\Types\Date;
use Project\Types\Month;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end routing through the real Router for the Phase 1b types: enum and
 * UUID resolve with the default casters; value objects require the opt-in
 * ValueObjectCaster.
 */
#[CoversClass(Router::class)]
final class CasterRoutingTest extends TestCase
{
	private const NS = 'Project\\Http\\';

	#[Test()]
	public function enum_segment_binds_to_the_enum(): void
	{
		$result = (new Router(self::NS))->route('get', '/cards/hearts');

		$this->assertSame(200, $result->status);
		$this->assertSame(self::NS . 'Cards\\GetOneAction', $result->route?->requestHandler);
		$this->assertEquals([Suit::Hearts], $result->route?->arguments);
	}

	#[Test()]
	public function unknown_enum_value_is_unprocessable(): void
	{
		$this->assertSame(422, (new Router(self::NS))->route('get', '/cards/joker')->status);
	}

	#[Test()]
	public function uuid_segment_binds_to_a_uuid(): void
	{
		$uuid = Uuid::uuid4()->toString();

		$result = (new Router(self::NS))->route('get', '/tokens/' . $uuid);

		$this->assertSame(200, $result->status);
		$arg = $result->route?->arguments[0] ?? null;
		$this->assertInstanceOf(UuidInterface::class, $arg);
		$this->assertSame($uuid, $arg->toString());
	}

	#[Test()]
	public function malformed_uuid_is_unprocessable(): void
	{
		$this->assertSame(422, (new Router(self::NS))->route('get', '/tokens/not-a-uuid')->status);
	}

	#[Test()]
	public function nested_value_object_consumes_multiple_segments_when_opted_in(): void
	{
		$router = new Router(Config::create(self::NS)->withCaster(new ValueObjectCaster()));

		$result = $router->route('get', '/posts/2026/August/27');

		$this->assertSame(200, $result->status);
		$date = $result->route?->arguments[0] ?? null;
		$this->assertInstanceOf(Date::class, $date);
		$this->assertSame(2026, $date->year->value);
		$this->assertSame(Month::August, $date->month);
		$this->assertSame('27', $date->day->value);
	}

	#[Test()]
	public function optional_value_object_defaults_when_no_segments(): void
	{
		$router = new Router(Config::create(self::NS)->withCaster(new ValueObjectCaster()));

		$result = $router->route('get', '/posts');

		$this->assertSame(200, $result->status);
		$this->assertSame([], $result->route?->arguments);
	}

	#[Test()]
	public function partial_value_object_is_bad_request(): void
	{
		$router = new Router(Config::create(self::NS)->withCaster(new ValueObjectCaster()));

		$this->assertSame(400, $router->route('get', '/posts/2026')->status);
	}

	#[Test()]
	public function value_object_route_needs_the_opt_in_caster(): void
	{
		// Without the ValueObjectCaster, no caster handles the Date param.
		$this->assertSame(422, (new Router(self::NS))->route('get', '/posts/2026/August/27')->status);
	}
}
