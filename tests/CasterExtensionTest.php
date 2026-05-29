<?php
declare(strict_types=1);

namespace Meraki\Http;

use Meraki\Http\Router\Config;
use Meraki\Http\Router\Caster;
use Meraki\Http\Router\CasterChain;
use Meraki\Http\Router\CastResult;
use Meraki\Http\Router\StringCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Proves the Caster extension point: Config ships default casters, withCaster()
 * prepends custom ones, and the router actually consults them when binding args.
 */
#[CoversClass(Config::class)]
#[CoversClass(Router::class)]
final class CasterExtensionTest extends TestCase
{
	private const NS = 'Project\\Http\\';

	#[Test()]
	public function config_ships_default_casters(): void
	{
		$casters = Config::create(self::NS)->casters;

		$this->assertCount(6, $casters);
		$this->assertInstanceOf(StringCaster::class, $casters[0]);
	}

	#[Test()]
	public function with_caster_prepends_so_it_takes_precedence(): void
	{
		$custom = $this->rejectIntCaster();

		$config = Config::create(self::NS)->withCaster($custom);

		$this->assertCount(7, $config->casters);
		$this->assertSame($custom, $config->casters[0]);
	}

	#[Test()]
	public function custom_caster_overrides_a_builtin_end_to_end(): void
	{
		// Default config: /archives/2026 binds 2026 as an int -> 200.
		$this->assertSame(200, (new Router(self::NS))->route('get', '/archives/2026')->status);

		// A custom int caster that rejects every value takes precedence over the
		// built-in IntCaster, so the same URL can no longer bind its int -> 422.
		$config = Config::create(self::NS)->withCaster($this->rejectIntCaster());

		$this->assertSame(422, (new Router($config))->route('get', '/archives/2026')->status);
	}

	private function rejectIntCaster(): Caster
	{
		return new class () implements Caster {
			public function supports(Type $type): bool
			{
				return $type->name === 'int';
			}

			public function cast(array $segments, Type $type, CasterChain $chain): CastResult
			{
				return CastResult::cannotCast();
			}
		};
	}
}
