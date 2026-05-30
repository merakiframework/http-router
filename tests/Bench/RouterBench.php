<?php
declare(strict_types=1);

namespace Meraki\Http\Bench;

use Meraki\Http\Router;
use Meraki\Http\Router\Config;
use Meraki\Http\Router\ValueObjectCaster;
use PhpBench\Attributes as Bench;

/**
 * Representative `Router::route()` benchmarks — a small surface that exercises
 * each routing-cost hot spot (segment walk, parent-args inheritance, casters,
 * value-object recursion, 405/204 method discovery).
 *
 * Run: `composer bench`
 *
 * Track a baseline:
 *   composer bench -- --store         # save the run as the new baseline
 *   composer bench -- --ref=tag       # compare against the saved baseline
 */
#[Bench\BeforeMethods('setUp')]
final class RouterBench
{
	private Router $router;
	private Router $routerWithVo;

	public function setUp(): void
	{
		$config = Config::create('Project\\Http\\')->withAdditionalMethods('propfind');
		$this->router = new Router($config);
		$this->routerWithVo = new Router($config->withCaster(new ValueObjectCaster()));
	}

	/**
	 * Fast path — a top-level Collection at a single namespace segment.
	 */
	#[Bench\Revs(1000)]
	#[Bench\Iterations(5)]
	#[Bench\Warmup(1)]
	public function benchStaticCollection(): void
	{
		$this->router->route('get', '/contacts');
	}

	/**
	 * Deep nested RESTful chain + variadic absorbing trailing filter segments —
	 * exercises the walk, parent-args inheritance, and variadic binding.
	 */
	#[Bench\Revs(1000)]
	#[Bench\Iterations(5)]
	#[Bench\Warmup(1)]
	public function benchDeepNestedRestfulWithVariadic(): void
	{
		$this->router->route('get', '/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control');
	}

	/**
	 * Value object consuming three segments via constructor recursion through
	 * the caster chain (scalar -> enum -> nested VO). Uses the opt-in caster.
	 */
	#[Bench\Revs(1000)]
	#[Bench\Iterations(5)]
	#[Bench\Warmup(1)]
	public function benchNestedValueObject(): void
	{
		$this->routerWithVo->route('get', '/posts/2026/August/27');
	}

	/**
	 * 404 miss — exercises every supported method in the 405-discovery loop
	 * before falling through to NotFound. Typically the slowest path.
	 */
	#[Bench\Revs(1000)]
	#[Bench\Iterations(5)]
	#[Bench\Warmup(1)]
	public function bench404Miss(): void
	{
		$this->router->route('get', '/no-such-resource');
	}

	/**
	 * Auto-synthesised OPTIONS — runs the poisoning-proof method-discovery loop
	 * across every supported method to populate the Allow list (204).
	 */
	#[Bench\Revs(1000)]
	#[Bench\Iterations(5)]
	#[Bench\Warmup(1)]
	public function benchOptionsAutoSynthesis(): void
	{
		$this->router->route('options', '/contacts');
	}
}
