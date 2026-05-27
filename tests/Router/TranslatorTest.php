<?php
declare(strict_types=1);

namespace Meraki\Http\Router;

use Meraki\Http\Router\Config;
use Meraki\Http\Router\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Translator::class)]
final class TranslatorTest extends TestCase
{
	private const REQUEST_HANDLERS_NAMESPACE = 'Project\\Http';

	#[Test()]
	#[DataProvider('noParentResourceOrChildSegments')]
	public function can_translate_with_no_next_segment_and_no_parent_resource(
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', '', $expectedChildResource, false);

		$this->assertEquals($expectedClass, $result);
	}

	public static function noParentResourceOrChildSegments(): array
	{
		return [
			'/' => ['', 'GetAction'],
			'/ping' => ['ping', 'GetAction'],
			'/pings' => ['pings', 'GetAllAction'],
			'/user' => ['user', 'GetAction'],
			'/users' => ['users', 'GetAllAction'],
		];
	}

	#[Test()]
	#[DataProvider('noChildSegmentsButParentResource')]
	public function can_translate_with_no_next_segment_and_parent_resource(
		string $expectedParentResource,
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', $expectedParentResource, $expectedChildResource, false);

		$this->assertEquals($expectedClass, $result);
	}

	public static function noChildSegmentsButParentResource(): array
	{
		return [
			'/ping/123/profile' => ['ping', 'profile', 'GetAction'],
			'/pings/123/profile' => ['pings', 'profile', 'GetAction'],
			'/user/123/profile' => ['user', 'profile', 'GetAction'],
			'/users/123/profile' => ['users', 'profile', 'GetAction'],
			'/ping/123/likes' => ['ping', 'likes', 'GetAllAction'],
			'/pings/123/likes' => ['pings', 'likes', 'GetAllAction'],
			'/user/123/likes' => ['user', 'likes', 'GetAllAction'],
			'/users/123/likes' => ['users', 'likes', 'GetAllAction'],
			'/states/qld/suburbs/emerald/registered-businesses' => ['suburbs', 'registered-businesses', 'GetAction'],
		];
	}

	#[Test()]
	#[DataProvider('noParentResourceButChildSegments')]
	public function can_translate_with_next_segment_and_no_parent_resource(
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', '', $expectedChildResource, true);

		$this->assertEquals($expectedClass, $result);
	}

	public static function noParentResourceButChildSegments(): array
	{
		return [
			'/archives/<2022>/<12>/<21>' => ['', 'GetAction'],
			'/ping/1' => ['ping', 'GetAction'],
			'/pings/1' => ['pings', 'GetOneAction'],
			'/user/1' => ['user', 'GetAction'],
			'/users/1' => ['users', 'GetOneAction'],
		];
	}

	#[Test()]
	#[DataProvider('parentResourceAndChildSegments')]
	public function can_translate_with_next_segment_and_parent_resource(
		string $expectedParentResource,
		string $expectedChildResource,
		string $expectedClass
	): void {
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', $expectedParentResource, $expectedChildResource, true);

		$this->assertEquals($expectedClass, $result);
	}

	public static function parentResourceAndChildSegments(): array
	{
		return [
			'/ping/1/profile/1' => ['ping', 'profile', 'GetAction'],
			'/pings/1/profile/1' => ['pings', 'profile', 'GetAction'],
			'/user/1/profile/1' => ['user', 'profile', 'GetAction'],
			'/users/1/profile/1' => ['users', 'profile', 'GetAction'],

			'/ping/1/likes/1' => ['ping', 'likes', 'GetAction'],
			'/pings/1/likes/1' => ['pings', 'likes', 'GetOneAction'],
			'/user/1/likes/1' => ['user', 'likes', 'GetAction'],
			'/users/1/likes/1' => ['users', 'likes', 'GetOneAction'],

			'/states/qld/suburbs/emerald/registered-businesses/cleaning/pest-control' => ['suburbs', 'registered-businesses', 'GetAction'],
		];
	}

	#[Test()]
	public function can_exclude_words_from_singular_plural_conversions(): void
	{
		$expectedChildResource = 'terms-and-conditions';
		$ns = self::REQUEST_HANDLERS_NAMESPACE;
		$config = Config::create($ns)->excludeFromConversion($expectedChildResource);
		$sut = new Translator($config);

		$result = $sut->translate('get', '', $expectedChildResource, false);

		$this->assertEquals('GetAction', $result);
	}

	#[Test()]
	public function compound_words_default_to_get_action_without_explicit_config(): void
	{
		$sut = $this->createTranslatorWithDefaultConfig();

		$result = $sut->translate('get', '', 'terms-and-conditions', false);

		$this->assertEquals('GetAction', $result);
	}

	#[Test()]
	public function can_define_invariant_plural_with_inflection_rule(): void
	{
		$config = Config::create(self::REQUEST_HANDLERS_NAMESPACE)
			->withInflectionRule('music', 'music');
		$sut = new Translator($config);

		$this->assertEquals('GetAllAction', $sut->translate('get', '', 'music', false));
	}

	#[Test()]
	public function can_override_irregular_plural_with_inflection_rule(): void
	{
		$config = Config::create(self::REQUEST_HANDLERS_NAMESPACE)
			->withInflectionRule('person', 'persons');
		$sut = new Translator($config);

		$this->assertEquals('GetAllAction', $sut->translate('get', '', 'persons', false));
		$this->assertEquals('GetAction', $sut->translate('get', '', 'person', false));
	}

	private function createTranslatorWithDefaultConfig(): Translator
	{
		return new Translator(Config::create(self::REQUEST_HANDLERS_NAMESPACE));
	}
}
