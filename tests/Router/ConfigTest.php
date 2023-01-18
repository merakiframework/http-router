<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Config;
use Meraki\Http\Router\Exception\InvalidArgument;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers Config::
 */
final class ConfigTest extends TestCase
{
	private const REQUEST_HANDLERS_NAMESPACE = 'Project\\Http';

	/**
	 * @test
	 */
	public function it_exists(): void
	{
		$sut = Config::class;

		$exists = class_exists($sut);

		$this->assertTrue($exists);
	}

	/**
	 * @test
	 */
	public function namespace_is_set(): void
	{
		$expectedNamespace = self::REQUEST_HANDLERS_NAMESPACE;
		$sut = Config::create($expectedNamespace);

		$actualNamespace = $sut->namespace;

		$this->assertEquals($expectedNamespace, $actualNamespace);
	}

	/**
	 * @test
	 */
	public function throws_error_if_namespace_is_empty(): void
	{
		$this->expectExceptionObject(InvalidArgument::namespaceValueIsMissing());

		$sut = Config::create('');
	}

	/**
	 * @test
	 */
	public function throws_error_if_trying_to_set_namespace_to_global_scope(): void
	{
		$this->expectExceptionObject(InvalidArgument::namespaceCannotBeInGlobalScope());

		$sut = Config::create('\\');
	}

	/**
	 * @test
	 */
	public function slashes_are_removed_from_beginning_of_namespace(): void
	{
		$expectedNamespace = self::REQUEST_HANDLERS_NAMESPACE;
		$sut = Config::create("\\$expectedNamespace");

		$actualNamespace = $sut->namespace;

		$this->assertEquals($expectedNamespace, $actualNamespace);
	}

	/**
	 * @test
	 */
	public function slashes_are_removed_from_end_of_namespace(): void
	{
		$expectedNamespace = self::REQUEST_HANDLERS_NAMESPACE;
		$sut = Config::create("$expectedNamespace\\");

		$actualNamespace = $sut->namespace;

		$this->assertEquals($expectedNamespace, $actualNamespace);
	}

	/**
	 * @test
	 */
	public function can_only_use_named_constructors(): void
	{
		$this->expectError();

		$sut = new Config(self::REQUEST_HANDLERS_NAMESPACE);
	}

	/**
	 * @test
	 */
	public function has_a_default_invoke_method(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('__invoke', $sut->invokeMethod);
	}

	/**
	 * @test
	 */
	public function has_a_default_logger(): void
	{
		$sut = $this->createConfig();

		$this->assertInstanceOf(NullLogger::class, $sut->logger);
	}

	/**
	 * @test
	 */
	public function has_a_default_prefix(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('', $sut->prefix);
	}

	/**
	 * @test
	 */
	public function has_a_default_suffix(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('Action', $sut->suffix);
	}

	/**
	 * @test
	 */
	public function has_a_default_plural_indicator_suffix(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('All', $sut->pluralIndicator);
	}

	/**
	 * @test
	 */
	public function has_a_default_singular_indicator_suffix(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('One', $sut->singularIndicator);
	}

	/**
	 * @test
	 */
	public function no_plural_words_are_excluded_from_conversion_by_default(): void
	{
		$sut = $this->createConfig();

		$this->assertEmpty($sut->excludedPluralWords);
	}

	/**
	 * @test
	 */
	public function no_singular_words_are_excluded_from_conversion_by_default(): void
	{
		$sut = $this->createConfig();

		$this->assertEmpty($sut->excludedSingularWords);
	}

	/**
	 * @test
	 */
	public function has_a_default_sub_namespace_for_the_root_path_of_a_url(): void
	{
		$sut = $this->createConfig();

		$this->assertEquals('\\Home', $sut->rootPathSubNamespace);
	}

	private function createConfig()
	{
		return Config::create(self::REQUEST_HANDLERS_NAMESPACE);
	}
}
